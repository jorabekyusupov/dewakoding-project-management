<?php

namespace App\Filament\Resources\TicketResource\Pages;

use App\Filament\Resources\TicketResource;
use App\Models\Project;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use App\Models\Ticket;
use App\Services\TicketNotificationService;

class CreateTicket extends CreateRecord
{
    protected static string $resource = TicketResource::class;

    protected function fillForm(): void
    {
        $copyFromId = request()->query('copy_from');

        if ($copyFromId) {
            $ticket = Ticket::find($copyFromId);

            if ($ticket) {
                $data = $ticket->toArray();
                unset(
                    $data['id'],
                    $data['uuid'],
                    $data['created_at'],
                    $data['updated_at'],
                    $data['created_by']
                );

                $data['assignees'] = $ticket->assignees()->pluck('users.id')->toArray();
                $this->form->fill($data);

                return;
            }
        }

        parent::fillForm();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        $ticket = parent::handleRecordCreation($data);

        if (!empty($data['assignees']) && !empty($data['project_id'])) {
            $project = Project::find($data['project_id']);

            if ($project) {
                $validAssignees = [];
                $invalidAssignees = [];

                foreach ($data['assignees'] as $userId) {
                    $isMember = $project->members()->where('users.id', $userId)->exists();

                    if ($isMember) {
                        $validAssignees[] = $userId;
                    } else {
                        $invalidAssignees[] = $userId;
                    }
                }

                if (!empty($validAssignees)) {
                    $ticket->assignees()->sync($validAssignees);
                }

                if (!empty($invalidAssignees)) {
                    Notification::make()
                        ->warning()
                        ->title('Some assignees removed')
                        ->body('Some selected users are not members of this project and have been removed from assignees.')
                        ->send();
                }

                if (empty($validAssignees)) {
                    $currentUserIsMember = $project->members()->where('users.id', auth()->id())->exists();

                    if ($currentUserIsMember) {
                        $ticket->assignees()->sync([auth()->id()]);

                        Notification::make()
                            ->info()
                            ->title('Auto-assigned')
                            ->body('No valid assignees found. You have been automatically assigned to this ticket.')
                            ->send();
                    }
                }
            }
        } else {
            if (!empty($data['project_id'])) {
                $project = Project::find($data['project_id']);
                $currentUserIsMember = $project?->members()->where('users.id', auth()->id())->exists();

                if ($currentUserIsMember) {
                    $ticket->assignees()->sync([auth()->id()]);
                }
            }
        }

        try {
            $ticket->refresh()->load(['project', 'priority', 'creator', 'assignees', 'status', 'epic']);
            app(TicketNotificationService::class)->notifyTicketCreated($ticket);
        } catch (\Throwable $exception) {
            report($exception);
        }

        return $ticket;
    }

    protected function getRedirectUrl(): string
    {
        $referer = request()->header('referer');

        if ($referer && str_contains($referer, 'project-board-page')) {
            return '/admin/project-board-page';
        }

        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Ticket created')
            ->body('The ticket has been created successfully.');
    }
}

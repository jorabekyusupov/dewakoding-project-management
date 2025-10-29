<?php

namespace App\Filament\Resources\ProjectResource\RelationManagers;

use App\Events\ProjectMemberAttached;
use App\Events\ProjectMemberDetached;
use App\Models\User;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use App\Services\TicketNotificationService;

class MembersRelationManager extends RelationManager
{
    protected static string $relationship = 'members';

    protected TicketNotificationService $ticketNotificationService;

    public function boot(TicketNotificationService $ticketNotificationService): void
    {
        $this->ticketNotificationService = $ticketNotificationService;
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return $ownerRecord->members_count ?? $ownerRecord->members()->count();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([

            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable()
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['name', 'email'])
                    ->label(__('resources.project.members.actions.add'))
                    ->after(function (Model $record) {
                        $project = $this->getOwnerRecord();
                        $user = User::find($record->id);
                        $assignedBy = auth()->user();
                        
                        if ($user) {
                            if ($assignedBy) {
                                ProjectMemberAttached::dispatch($project, $user, $assignedBy);
                            }

                            try {
                                $this->ticketNotificationService->notifyProjectMemberAdded($project, $user);
                            } catch (\Throwable $exception) {
                                report($exception);
                            }
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\DetachAction::make()
                    ->label(__('resources.project.members.actions.remove'))
                    ->after(function (Model $record) {
                        $project = $this->getOwnerRecord();
                        $user = User::find($record->id);
                        $removedBy = auth()->user();
                        
                        if ($user && $removedBy) {
                            ProjectMemberDetached::dispatch($project, $user, $removedBy);
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make()
                        ->label(__('resources.project.members.actions.remove_selected')),
                ]),
            ]);
    }
}

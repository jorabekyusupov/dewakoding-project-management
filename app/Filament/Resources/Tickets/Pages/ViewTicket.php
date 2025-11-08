<?php

namespace App\Filament\Resources\Tickets\Pages;

use Filament\Actions\EditAction;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use App\Filament\Pages\ProjectBoard;
use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Ticket;
use App\Models\TicketComment;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\RichEditor;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewTicket extends ViewRecord
{
    protected static string $resource = TicketResource::class;

    public ?int $editingCommentId = null;

    public function editComment(int $commentId): void
    {
        $comment = TicketComment::find($commentId);

        if (!$comment) {
            Notification::make()
                ->title('Comment not found')
                ->danger()
                ->send();
            return;
        }

        if ($comment->user_id !== auth()->id() && !auth()->user()->hasRole(['super_admin'])) {
            Notification::make()
                ->title('You do not have permission to edit this comment')
                ->danger()
                ->send();
            return;
        }

        $this->editingCommentId = $commentId;
        $this->mountAction('editCommentAction', ['commentId' => $commentId]);
    }

    public function deleteComment(int $commentId): void
    {
        $comment = TicketComment::find($commentId);

        if (!$comment) {
            Notification::make()
                ->title('Comment not found')
                ->danger()
                ->send();
            return;
        }

        if ($comment->user_id !== auth()->id() && !auth()->user()->hasRole(['super_admin'])) {
            Notification::make()
                ->title('You do not have permission to delete this comment')
                ->danger()
                ->send();
            return;
        }

        $comment->delete();

        Notification::make()
            ->title('Comment deleted successfully')
            ->success()
            ->send();

        $this->dispatch('$refresh');
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->visible(function () {
                    $ticket = $this->getRecord();

                    return auth()->user()->hasRole(['super_admin'])
                        || $ticket->created_by === auth()->id()
                        || $ticket->assignees()->where('users.id', auth()->id())->exists();
                }),

            Action::make('back')
                ->label('Back to Board')
                ->color('gray')
                ->url(fn() => ProjectBoard::getUrl(['project_id' => $this->record->project_id])),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Ticket Information')
                    ->icon('heroicon-o-ticket')
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2, 'lg' => 3])
                            ->schema([
                                TextEntry::make('uuid')
                                    ->label('Ticket ID')
                                    ->copyable()
                                    ->icon('heroicon-o-hashtag'),

                                TextEntry::make('name')
                                    ->label('Ticket Name')
                                    ->icon('heroicon-o-document-text')
                                    ->weight('bold'),

                                TextEntry::make('project.name')
                                    ->label('Project')
                                    ->icon('heroicon-o-folder'),
                            ]),
                    ]),

                Section::make('Status & Assignment')
                    ->icon('heroicon-o-user-group')
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2, 'lg' => 4])
                            ->schema([
                                TextEntry::make('status.name')
                                    ->label('Status')
                                    ->badge()
                                    ->color(fn($record) => $record->status?->color ?? 'gray'),

                                TextEntry::make('assignees.name')
                                    ->label('Assigned To')
                                    ->badge()
                                    ->separator(',')
                                    ->default('Unassigned')
                                    ->color('info'),

                                TextEntry::make('creator.name')
                                    ->label('Created By')
                                    ->default('Unknown')
                                    ->icon('heroicon-o-user'),

                                TextEntry::make('due_date')
                                    ->label('Due Date')
                                    ->date('d M Y')
                                    ->icon('heroicon-o-calendar')
                                    ->color(fn($record) => $record->due_date && $record->due_date->isPast() ? 'danger' : 'success'),
                            ]),
                    ]),

                Section::make('Description')
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        TextEntry::make('description')
                            ->hiddenLabel()
                            ->html()
                            ->columnSpanFull()
                            ->placeholder('No description provided'),
                    ])
                    ->columnSpanFull(),

                Section::make('Comments')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->description('Discussion about this ticket')
                    ->schema([
                        TextEntry::make('comments_list')
                            ->hiddenLabel()
                            ->state(function (Ticket $record) {
                                if (method_exists($record, 'comments')) {
                                    return $record->comments()->with('user')->oldest()->get();
                                }

                                return collect();
                            })
                            ->view('filament.resources.ticket-resource.comments-section')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull()
                    ->collapsible(),

                Grid::make(['default' => 1, 'lg' => 2])
                    ->schema([
                        Section::make('Metadata')
                            ->icon('heroicon-o-information-circle')
                            ->collapsible()
                            ->collapsed()
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('Created At')
                                    ->dateTime('d M Y H:i')
                                    ->icon('heroicon-o-clock'),

                                TextEntry::make('updated_at')
                                    ->label('Updated At')
                                    ->dateTime('d M Y H:i')
                                    ->icon('heroicon-o-arrow-path'),

                                TextEntry::make('epic.name')
                                    ->label('Epic')
                                    ->default('No Epic')
                                    ->badge()
                                    ->color('warning')
                                    ->icon('heroicon-o-flag'),
                            ]),

                        Section::make('Status History')
                            ->icon('heroicon-o-clock')
                            ->collapsible()
                            ->collapsed()
                            ->schema([
                                TextEntry::make('histories')
                                    ->hiddenLabel()
                                    ->view('filament.resources.ticket-resource.timeline-history')
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }
}

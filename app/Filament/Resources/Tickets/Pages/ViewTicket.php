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

    protected function getHeaderActions(): array
    {
        $ticket = $this->getRecord();
        $project = $ticket->project;

        $canComment = $project->members()->where('users.id', auth()->id())->exists();

        return [
            Actions\Action::make('download_file')
                ->label('Download File')
                ->translateLabel()
                ->icon('heroicon-c-folder-arrow-down')
                ->visible(fn (Ticket $record): bool => !empty($record->file))
                ->action(function ($record) {
                    return response()->download(storage_path('app/public/' . $record->file));
                }),
            EditAction::make()
                ->visible(function () {
                    $ticket = $this->getRecord();

                    return auth()->user()->hasRole(['super_admin'])
                        || $ticket->created_by === auth()->id()
                        || $ticket->assignees()->where('users.id', auth()->id())->exists();
                }),

            Action::make('addComment')
                ->label(__('resources.ticket.view.actions.add_comment'))
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('success')
                ->schema([
                    RichEditor::make('comment')
                        ->required(),
                ])
                ->action(function (array $data) {
                    $ticket = $this->getRecord();

                    $comment = $ticket->comments()->create([
                        'user_id' => auth()->id(),
                        'comment' => $data['comment']
                    ]);

                    auth()->user()->notifications()
                        ->where('data->ticket_id', $ticket->id)
                        ->whereNull('read_at')
                        ->update(['read_at' => now()]);

                    Notification::make()
                        ->title(__('resources.ticket.view.notifications.comment_added'))
                        ->success()
                        ->send();
                })
                ->visible($canComment),

            Action::make('back')
                ->label(__('resources.ticket.view.actions.back_to_board'))
                ->color('gray')
                ->url(fn () => ProjectBoard::getUrl(['project_id' => $this->record->project_id])),
        ];
    }

    public function handleEditComment($id)
    {
        $comment = TicketComment::find($id);

        if (! $comment) {
            Notification::make()
                ->title(__('resources.ticket.view.notifications.comment_not_found'))
                ->danger()
                ->send();

            return;
        }

        if ($comment->user_id !== auth()->id() && !auth()->user()->hasRole(['super_admin'])) {
            Notification::make()
                ->title(__('resources.ticket.view.notifications.edit_forbidden'))
                ->danger()
                ->send();

            return;
        }

        $this->editingCommentId = $id;
        $this->mountAction('editComment', ['commentId' => $id]);
    }

    public function handleDeleteComment($id)
    {
        $comment = TicketComment::find($id);

        if (! $comment) {
            Notification::make()
                ->title(__('resources.ticket.view.notifications.comment_not_found'))
                ->danger()
                ->send();

            return;
        }

        if ($comment->user_id !== auth()->id() && !auth()->user()->hasRole(['super_admin'])) {
            Notification::make()
                ->title(__('resources.ticket.view.notifications.delete_forbidden'))
                ->danger()
                ->send();

            return;
        }

        $comment->delete();

        Notification::make()
            ->title(__('resources.ticket.view.notifications.comment_deleted'))
            ->success()
            ->send();

        $this->redirect($this->getResource()::getUrl('view', ['record' => $this->getRecord()]));
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
                                    ->color(fn ($record) => $record->status?->color ?? 'gray'),

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
                                    ->color(fn ($record) => $record->due_date && $record->due_date->isPast() ? 'danger' : 'success'),
                            ]),
                    ]),

                Section::make(__('resources.ticket.view.sections.description'))
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        TextEntry::make('description')
                            ->hiddenLabel()
                            ->html()
                            ->columnSpanFull()
                            ->placeholder('No description provided'),
                    ])
                    ->columnSpanFull(),

                Section::make(__('resources.ticket.view.sections.comments'))
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->description(__('resources.ticket.view.sections.comments_description'))
                    ->schema([
                        TextEntry::make('comments_list')
                            ->label(__('resources.ticket.view.fields.recent_comments'))
                            ->state(function (Ticket $record) {
                                if (method_exists($record, 'comments')) {
                                    return $record->comments()->with('user')->oldest()->get();
                                }

                                return collect();
                            })
                            ->view('filament.resources.ticket-resource.latest-comments')
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

    protected function getActions(): array
    {
        return [
            Action::make('editComment')
                ->label(__('resources.ticket.view.actions.edit_comment'))
                ->mountUsing(function (Schema $schema, array $arguments) {
                    $commentId = $arguments['commentId'] ?? null;

                    if (! $commentId) {
                        return;
                    }

                    $comment = TicketComment::find($commentId);

                    if (! $comment) {
                        return;
                    }

                    $schema->fill([
                        'commentId' => $comment->id,
                        'comment' => $comment->comment,
                    ]);
                })
                ->schema([
                    Hidden::make('commentId')
                        ->required(),
                    RichEditor::make('comment')
                        ->label(__('resources.ticket.view.fields.comment'))
                        ->toolbarButtons([
                            'blockquote',
                            'bold',
                            'bulletList',
                            'codeBlock',
                            'h2',
                            'h3',
                            'italic',
                            'link',
                            'orderedList',
                            'redo',
                            'strike',
                            'underline',
                            'undo',
                        ])
                        ->required(),
                ])
                ->action(function (array $data) {
                    $comment = TicketComment::find($data['commentId']);

                    if (! $comment) {
                        Notification::make()
                            ->title(__('resources.ticket.view.notifications.comment_not_found'))
                            ->danger()
                            ->send();

                        return;
                    }

                    // Check permissions
                    if (! auth()->user()->can('update', $comment)) {
                        Notification::make()
                            ->title(__('resources.ticket.view.notifications.edit_forbidden'))
                            ->danger()
                            ->send();

                        return;
                    }

                    $comment->update([
                        'comment' => $data['comment'],
                    ]);

                    Notification::make()
                        ->title(__('resources.ticket.view.notifications.comment_updated'))
                        ->success()
                        ->send();

                    // Reset editingCommentId
                    $this->editingCommentId = null;

                    // Refresh the page
                    $this->redirect($this->getResource()::getUrl('view', ['record' => $this->getRecord()]));
                })
                ->modalWidth('lg')
                ->modalHeading(__('resources.ticket.view.modals.edit_comment.heading'))
                ->modalSubmitActionLabel(__('resources.ticket.view.modals.edit_comment.submit'))
                ->color('success')
                ->icon('heroicon-o-pencil'),
        ];
    }
}

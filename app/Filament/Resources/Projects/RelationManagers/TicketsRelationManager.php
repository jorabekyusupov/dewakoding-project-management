<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\CreateAction;
use Filament\Actions\Action;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Actions;
use Exception;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Radio;
use App\Models\Epic;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\TicketPriority;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use App\Imports\TicketsImport;
use App\Exports\TicketTemplateExport;
use Filament\Forms\Components\FileUpload;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;
use App\Services\TicketNotificationService;

class TicketsRelationManager extends RelationManager
{
    protected static string $relationship = 'tickets';

    protected TicketNotificationService $ticketNotificationService;

    protected ?int $editingTicketOriginalStatusId = null;

    protected ?string $editingTicketOriginalStatusName = null;
    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('tickets');
    }
    public function boot(TicketNotificationService $ticketNotificationService): void
    {
        $this->ticketNotificationService = $ticketNotificationService;
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return $ownerRecord->tickets_count ?? $ownerRecord->tickets()->count();
    }

    public function form(Schema $schema): Schema
    {
        $projectId = $this->getOwnerRecord()->id;

        $defaultStatus = TicketStatus::where('project_id', $projectId)->first();
        $defaultStatusId = $defaultStatus ? $defaultStatus->id : null;

        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->label(__('resources.project.tickets.form.name')),
                
                Select::make('ticket_status_id')
                    ->label(__('resources.project.tickets.form.status'))
                    ->options(function () use ($projectId) {
                        return TicketStatus::where('project_id', $projectId)
                            ->pluck('name', 'id')
                            ->toArray();
                    })
                    ->default($defaultStatusId)
                    ->required()
                    ->searchable(),
                
                Select::make('epic_id')
                    ->label(__('resources.project.tickets.form.epic'))
                    ->options(function () use ($projectId) {
                        return Epic::where('project_id', $projectId)
                            ->pluck('name', 'id')
                            ->toArray();
                    })
                    ->nullable(),
                
                // UPDATED: Multi-user assignment
                Select::make('assignees')
                    ->label(__('resources.project.tickets.form.assignees'))
                    ->multiple()
                    ->relationship(
                        name: 'assignees',
                        titleAttribute: 'name',
                        modifyQueryUsing: function ($query) {
                            $projectId = $this->getOwnerRecord()->id;
                            // Only show project members
                            return $query->whereHas('projects', function ($query) use ($projectId) {
                                $query->where('projects.id', $projectId);
                            });
                        }
                    )
                    ->searchable()
                    ->preload()
                    ->default(function ($record) {
                        if ($record && $record->exists) {
                            return $record->assignees->pluck('id')->toArray();
                        }
                        
                        // Auto-assign current user if they're a project member
                        $project = $this->getOwnerRecord();
                        $isCurrentUserMember = $project->members()->where('users.id', auth()->id())->exists();
                        
                        return $isCurrentUserMember ? [auth()->id()] : [];
                    })
                    ->helperText(__('resources.project.tickets.form.assignees_help')),
                
                DatePicker::make('start_date')
                    ->label(__('resources.project.tickets.form.start_date'))
                    ->nullable(),
                
                DatePicker::make('due_date')
                    ->label(__('resources.project.tickets.form.due_date'))
                    ->nullable()
                    ->afterOrEqual('start_date'),
                
                RichEditor::make('description')
                    ->columnSpanFull()
                    ->nullable(),

                // Show created by in edit mode
                Select::make('created_by')
                    ->label(__('resources.project.tickets.form.created_by'))
                    ->relationship('creator', 'name')
                    ->disabled()
                    ->hiddenOn('create'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('uuid')
                    ->label(__('resources.project.tickets.columns.id'))
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('status.name')
                    ->badge()
                    ->color(fn ($record) => match ($record->status?->name) {
                        'To Do' => 'warning',
                        'In Progress' => 'info',
                        'Review' => 'primary',
                        'Done' => 'success',
                        default => 'gray',
                    })
                    ->sortable(),
                
                TextColumn::make('epic.name')
                    ->label(__('resources.project.tickets.columns.epic'))
                    ->badge()
                    ->color('warning')
                    ->placeholder(__('resources.project.tickets.values.no_epic'))
                    ->sortable()
                    ->searchable(),
                
                TextColumn::make('assignees.name')
                    ->label(__('resources.project.tickets.columns.assignees'))
                    ->badge()
                    ->separator(',')
                    ->expandableLimitedList()
                    ->searchable(),
                
                TextColumn::make('creator.name')
                    ->label(__('resources.project.tickets.columns.created_by'))
                    ->sortable()
                    ->toggleable(),
                
                TextColumn::make('start_date')
                    ->label(__('resources.project.tickets.columns.start_date'))
                    ->date()
                    ->sortable(),
                
                TextColumn::make('due_date')
                    ->label(__('resources.project.tickets.columns.due_date'))
                    ->date()
                    ->sortable(),
                
                TextColumn::make('created_at')
                    ->label(__('resources.project.tickets.columns.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('ticket_status_id')
                    ->label(__('resources.project.tickets.filters.status'))
                    ->options(function () {
                        $projectId = $this->getOwnerRecord()->id;

                        return TicketStatus::where('project_id', $projectId)
                            ->pluck('name', 'id')
                            ->toArray();
                    }),
                
                // UPDATED: Filter by assignees
                SelectFilter::make('assignees')
                    ->label(__('resources.project.tickets.filters.assignee'))
                    ->relationship('assignees', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload(),
                
                // Filter by creator
                SelectFilter::make('created_by')
                    ->label(__('resources.project.tickets.filters.created_by'))
                    ->relationship('creator', 'name')
                    ->searchable()
                    ->preload(),
                
                // Filter by epic
                SelectFilter::make('epic_id')
                    ->label(__('resources.project.tickets.filters.epic'))
                    ->options(function () {
                        $projectId = $this->getOwnerRecord()->id;
                        return Epic::where('project_id', $projectId)
                            ->pluck('name', 'id')
                            ->toArray();
                    }),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateDataUsing(function (array $data): array {
                        // Set project_id and created_by
                        $data['project_id'] = $this->getOwnerRecord()->id;
                        $data['created_by'] = auth()->id();
                        return $data;
                    })
                    ->after(function (Model $record): void {
                        if (! $record instanceof Ticket) {
                            return;
                        }

                        $record->load(['project', 'priority', 'creator', 'assignees', 'status', 'epic']);
                        $this->sendTicketCreatedNotification($record);
                    }),
                
                // NEW: Import from Excel action
                Action::make('import_tickets')
                    ->label(__('resources.project.tickets.actions.import.label'))
                    ->icon('heroicon-m-arrow-up-tray')
                    ->color('success')
                    ->schema([
                        Section::make(__('resources.project.tickets.actions.import.section_heading'))
                            ->description(__('resources.project.tickets.actions.import.section_description'))
                            ->schema([
                                Actions::make([
                                    Action::make('download_template')
                                        ->label(__('resources.project.tickets.actions.import.download_template'))
                                        ->icon('heroicon-m-arrow-down-tray')
                                        ->color('gray')
                                        ->action(function (RelationManager $livewire) {
                                            $project = $livewire->getOwnerRecord();
                                            $filename = 'ticket-import-template-' . str($project->name)->slug() . '.xlsx';
                                            
                                            return Excel::download(
                                                new TicketTemplateExport($project),
                                                $filename
                                            );
                                        })
                                ])->fullWidth(),
                                
                                FileUpload::make('excel_file')
                                    ->label(__('resources.project.tickets.actions.import.file_label'))
                                    ->helperText(__('resources.project.tickets.actions.import.file_help'))
                                    ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel'])
                                    ->maxSize(5120) // 5MB
                                    ->required()
                                    ->disk('local')
                                    ->directory('temp-imports')
                                    ->visibility('private'),
                            ]),
                    ])
                    ->action(function (array $data, RelationManager $livewire) {
                        $project = $livewire->getOwnerRecord();
                        $filePath = Storage::disk('local')->path($data['excel_file']);
                        
                        try {
                            $import = new TicketsImport($project);
                            Excel::import($import, $filePath);

                            $importedCount = $import->getImportedCount();
                            $errors = $import->errors();
                            $failures = $import->failures();

                            // Clean up uploaded file
                            Storage::disk('local')->delete($data['excel_file']);

                            if ($importedCount > 0) {
                                $message = __('resources.project.tickets.import.success', [
                                    'count' => $importedCount,
                                    'project' => $project->name,
                                ]);

                                if (! empty($errors) || ! empty($failures)) {
                                    $message .= PHP_EOL . PHP_EOL . __('resources.project.tickets.import.partial_warning');
                                }

                                Notification::make()
                                    ->title(__('resources.project.tickets.import.completed_title'))
                                    ->body($message)
                                    ->success()
                                    ->send();
                            } else {
                                $importErrors = $import->errors();
                                $importFailures = $import->failures();

                                $lines = [
                                    __('resources.project.tickets.import.none_imported'),
                                ];

                                if (! empty($importFailures)) {
                                    $lines[] = __('resources.project.tickets.import.validation_heading');
                                    foreach ($importFailures as $failure) {
                                        $lines[] = __('resources.project.tickets.import.validation_row', [
                                            'row' => $failure->row(),
                                            'errors' => implode(', ', $failure->errors()),
                                        ]);
                                    }
                                }

                                if (! empty($importErrors)) {
                                    $lines[] = __('resources.project.tickets.import.processing_heading');
                                    foreach ($importErrors as $error) {
                                        $lines[] = __('resources.project.tickets.import.processing_item', [
                                            'message' => $error,
                                        ]);
                                    }
                                }

                                if (empty($importFailures) && empty($importErrors)) {
                                    $lines[] = __('resources.project.tickets.import.generic_help');
                                }

                                Notification::make()
                                    ->title(__('resources.project.tickets.import.failed_title'))
                                    ->body(implode(PHP_EOL, $lines))
                                    ->warning()
                                    ->persistent()
                                    ->send();
                            }
                        } catch (Exception $e) {
                            // Clean up uploaded file on error
                            Storage::disk('local')->delete($data['excel_file']);

                            Notification::make()
                                ->title(__('resources.project.tickets.import.error_title'))
                                ->body(__('resources.project.tickets.import.error_body', ['message' => $e->getMessage()]))
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    
                    BulkAction::make('updateStatus')
                        ->label(__('resources.project.tickets.bulk.update_status.label'))
                        ->icon('heroicon-o-arrow-path')
                        ->form([
                            Select::make('ticket_status_id')
                                ->label(__('resources.project.tickets.filters.status'))
                                ->options(function (RelationManager $livewire) {
                                    $projectId = $livewire->getOwnerRecord()->id;

                                    return TicketStatus::where('project_id', $projectId)
                                        ->pluck('name', 'id')
                                        ->toArray();
                                })
                                ->required(),
                        ])
                        ->action(function (array $data, Collection $records): void {
                            $newStatusId = $data['ticket_status_id'] ?? null;

                            if (empty($newStatusId)) {
                                return;
                            }

                            $updatedCount = 0;

                            foreach ($records as $record) {
                                if (! $record instanceof Ticket) {
                                    continue;
                                }

                                if ((int) $record->ticket_status_id === (int) $newStatusId) {
                                    continue;
                                }

                                $record->load(['project', 'priority', 'creator', 'assignees', 'status', 'epic']);
                                $oldStatus = $record->status?->name ?? 'N/A';

                                $record->update([
                                    'ticket_status_id' => $newStatusId,
                                ]);

                                $record->refresh()->load(['project', 'priority', 'creator', 'assignees', 'status', 'epic']);
                                $newStatus = $record->status?->name ?? 'N/A';
                                $this->sendTicketStatusChangedNotification($record, $oldStatus, $newStatus);
                                $updatedCount++;
                            }
                            
                            if ($updatedCount > 0) {
                                Notification::make()
                                    ->success()
                                    ->title(__('resources.project.tickets.bulk.update_status.success_title'))
                                    ->body(trans_choice('resources.project.tickets.bulk.update_status.success_body', $updatedCount, [
                                        'count' => $updatedCount,
                                    ]))
                                    ->send();
                            } else {
                                Notification::make()
                                    ->info()
                                    ->title(__('resources.project.tickets.bulk.update_status.no_changes_title'))
                                    ->body(__('resources.project.tickets.bulk.update_status.no_changes_body'))
                                    ->send();
                            }
                        }),
                    
                    // NEW: Bulk assign users
                    BulkAction::make('assignUsers')
                        ->label(__('resources.project.tickets.bulk.assign_users.label'))
                        ->icon('heroicon-o-user-plus')
                        ->form([
                            Select::make('assignees')
                                ->label(__('resources.project.tickets.bulk.assign_users.assignees'))
                                ->multiple()
                                ->options(function (RelationManager $livewire) {
                                    return $livewire->getOwnerRecord()
                                        ->members()
                                        ->pluck('name', 'users.id')
                                        ->toArray();
                                })
                                ->searchable()
                                ->preload()
                                ->required(),
                            
                            Radio::make('assignment_mode')
                                ->label(__('resources.project.tickets.bulk.assign_users.mode_label'))
                                ->options([
                                    'replace' => __('resources.project.tickets.bulk.assign_users.mode_replace'),
                                    'add' => __('resources.project.tickets.bulk.assign_users.mode_add'),
                                ])
                                ->default('add')
                                ->required(),
                        ])
                        ->action(function (array $data, Collection $records) {
                            foreach ($records as $record) {
                                if ($data['assignment_mode'] === 'replace') {
                                    $record->assignees()->sync($data['assignees']);
                                } else {
                                    $record->assignees()->syncWithoutDetaching($data['assignees']);
                                }
                            }
                            
                            $affected = count($records);

                            Notification::make()
                                ->success()
                                ->title(__('resources.project.tickets.bulk.assign_users.success_title'))
                                ->body(trans_choice('resources.project.tickets.bulk.assign_users.success_body', $affected, [
                                    'count' => $affected,
                                ]))
                                ->send();
                        }),
                    BulkAction::make('updatePriority')
                        ->label(__('resources.project.tickets.bulk.update_priority.label'))
                        ->icon('heroicon-o-flag')
                        ->form([
                            Select::make('priority_id')
                                ->label(__('resources.project.tickets.bulk.update_priority.field'))
                                ->options(TicketPriority::pluck('name', 'id')->toArray())
                                ->nullable(),
                        ])
                        ->action(function (array $data, Collection $records) {
                            foreach ($records as $record) {
                                $record->update([
                                    'priority_id' => $data['priority_id'],
                                ]);
                            }
                        }),
                    
                    BulkAction::make('assignToEpic')
                        ->label(__('resources.project.tickets.bulk.assign_epic.label'))
                        ->icon('heroicon-o-bookmark')
                        ->form([
                            Select::make('epic_id')
                                ->label(__('resources.project.tickets.bulk.assign_epic.field'))
                                ->options(function (RelationManager $livewire) {
                                    $projectId = $livewire->getOwnerRecord()->id;
                                    return Epic::where('project_id', $projectId)
                                        ->pluck('name', 'id')
                                        ->toArray();
                                })
                                ->searchable()
                                ->preload()
                                ->nullable()
                                ->helperText(__('resources.project.tickets.bulk.assign_epic.help')),
                        ])
                        ->action(function (array $data, Collection $records) {
                            foreach ($records as $record) {
                                $record->update([
                                    'epic_id' => $data['epic_id'],
                                ]);
                            }
                            
                            $epicName = $data['epic_id']
                                ? optional(Epic::find($data['epic_id']))->name
                                : null;

                            $epicName ??= __('resources.project.tickets.values.no_epic');
                            $affected = count($records);

                            Notification::make()
                                ->success()
                                ->title(__('resources.project.tickets.bulk.assign_epic.success_title'))
                                ->body(trans_choice('resources.project.tickets.bulk.assign_epic.success_body', $affected, [
                                    'count' => $affected,
                                    'epic' => $epicName,
                                ]))
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    private function sendTicketCreatedNotification(Ticket $ticket): void
    {
        try {
            $this->ticketNotificationService->notifyTicketCreated($ticket);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    private function sendTicketStatusChangedNotification(Ticket $ticket, string $oldStatus, string $newStatus): void
    {
        try {
            $this->ticketNotificationService->notifyTicketStatusChanged($ticket, $oldStatus, $newStatus);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    private function resetEditingTicketStatusSnapshot(): void
    {
        $this->editingTicketOriginalStatusId = null;
        $this->editingTicketOriginalStatusName = null;
    }
}






























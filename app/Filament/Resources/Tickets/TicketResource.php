<?php

namespace App\Filament\Resources\Tickets;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\Tickets\Pages\ListTickets;
use App\Filament\Resources\Tickets\Pages\CreateTicket;
use App\Filament\Resources\Tickets\Pages\ViewTicket;
use App\Filament\Resources\Tickets\Pages\EditTicket;
use App\Filament\Resources\TicketResource\Pages;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\TicketPriority;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use App\Models\Epic;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use UnitEnum;

class TicketResource extends Resource
{
    protected static ?string $model = Ticket::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationLabel = null;

    protected static string | \UnitEnum | null $navigationGroup = 'Project Management';


    protected static ?int $navigationSort = 5;

    public static function getNavigationLabel(): string
    {
        return __('resources.tickets.navigation_label');
    }

public static function getNavigationGroup(): string|UnitEnum|null
{
    return __('navigation.project_management');
}

    /**
     * @return string|null
     */
    public static function getPluralLabel(): ?string
    {
        return __('tickets');
    }

    public static function getLabel(): ?string
    {
        return __('ticket');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (!auth()->user()->hasRole(['super_admin'])) {
            $query->where(function ($query) {
                $query->whereHas('assignees', function ($query) {
                    $query->where('users.id', auth()->id());
                })
                    ->orWhere('created_by', auth()->id())
                    ->orWhereHas('project.members', function ($query) {
                        $query->where('users.id', auth()->id());
                    });
            });
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        $projectId = request()->query('project_id') ?? request()->input('project_id');
        $statusId = request()->query('ticket_status_id') ?? request()->input('ticket_status_id');

        return $schema
            ->components([
                Select::make('project_id')
                    ->label(__('resources.tickets.form.project'))

                    ->options(function () {
                        if (auth()->user()->hasRole(['super_admin'])) {
                            return Project::pluck('name', 'id')->toArray();
                        }

                        return auth()->user()->projects()->pluck('name', 'projects.id')->toArray();
                    })
                    ->default($projectId)
                    ->required()
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function (callable $set) {
                        $set('ticket_status_id', null);
                        $set('assignees', []);
                        $set('epic_id', null);
                    }),

                Select::make('ticket_status_id')
                    ->label(__('resources.tickets.form.status'))
                    ->options(function ($get) {
                        $projectId = $get('project_id');
                        if (!$projectId) {
                            return [];
                        }

                        return TicketStatus::where('project_id', $projectId)
                            ->pluck('name', 'id')
                            ->toArray();
                    })
                    ->default($statusId)
                    ->required()
                    ->searchable()
                    ->preload(),

                Select::make('priority_id')
                    ->label(__('resources.tickets.form.priority'))
                    ->options(TicketPriority::pluck('name', 'id')->toArray())
                    ->searchable()
                    ->preload()
                    ->nullable(),

                Select::make('epic_id')
                    ->label(__('resources.tickets.form.epic'))
                    ->options(function (callable $get) {
                        $projectId = $get('project_id');

                        if (!$projectId) {
                            return [];
                        }

                        return Epic::where('project_id', $projectId)
                            ->pluck('name', 'id')
                            ->toArray();
                    })
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->hidden(fn(callable $get): bool => !$get('project_id')),

                TextInput::make('name')
                    ->label(__('resources.tickets.form.name'))
                    ->required()
                    ->maxLength(255),

                RichEditor::make('description')
                    ->label(__('resources.tickets.form.description'))
                    ->fileAttachmentsDirectory('attachments')
                    ->columnSpanFull(),
                Forms\Components\FileUpload::make('file')
                    ->label(__('file'))
                    ->columnSpanFull()
                    ->disk('public')
                    ->directory('task_files')
                    ->getUploadedFileNameForStorageUsing(
                        fn(TemporaryUploadedFile $file): string => (string)str($file->getClientOriginalName())
                            ->prepend('task-file-'),
                    ),
                // Multi-user assignment
                Select::make('assignees')
                    ->label(__('resources.tickets.form.assignees'))
                    ->multiple()
                    ->relationship(
                        name: 'assignees',
                        titleAttribute: 'name',
                        modifyQueryUsing: function (Builder $query, callable $get) {
                            $projectId = $get('project_id');
                            if (!$projectId) {
                                return $query->whereRaw('1 = 0');
                            }

                            $project = Project::find($projectId);
                            if (!$project) {
                                return $query->whereRaw('1 = 0');
                            }

                            return $query->whereHas('projects', function ($query) use ($projectId) {
                                $query->where('projects.id', $projectId);
                            });
                        }
                    )
                    ->searchable()
                    ->preload()
                    ->helperText(__('resources.tickets.form.assignees_help'))
                    ->hidden(fn(callable $get): bool => !$get('project_id'))
                    ->live(),
                
                DatePicker::make('start_date')
                    ->label(__('resources.tickets.form.start_date'))
                    ->default(now())
                    ->nullable(),
                
                DatePicker::make('due_date')
                    ->label(__('resources.tickets.form.due_date'))
                    ->nullable(),
                Select::make('created_by')
                    ->label(__('resources.tickets.form.created_by'))
                    ->relationship('creator', 'name')
                    ->disabled()
                    ->hiddenOn('create'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('uuid')
                    ->label(__('resources.tickets.form.ticket_id'))
                    ->searchable()
                    ->copyable(),

                TextColumn::make('project.name')
                    ->label(__('resources.tickets.form.project'))
                    ->sortable()
                    ->searchable(),

                TextColumn::make('name')
                    ->label(__('resources.tickets.columns.name'))
                    ->searchable()
                    ->limit(30),

                TextColumn::make('status.name')
                    ->label(__('resources.tickets.form.status'))
                    ->badge()
                    ->sortable(),

                TextColumn::make('priority.name')
                    ->label(__('resources.tickets.form.priority'))
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'Высокий' => 'danger',
                        'Средний' => 'warning',
                        'Низкий' => 'success',
                        default => 'gray',
                    })
                    ->sortable()
                    ->default('—')
                    ->placeholder(__('resources.tickets.placeholders.no_priority')),

                // Display multiple assignees
                TextColumn::make('assignees.name')
                    ->label(__('resources.tickets.columns.assign_to'))
                    ->badge()
                    ->separator(',')
                    ->limitList(2)
                    ->expandableLimitedList()
                    ->searchable(),

                TextColumn::make('creator.name')
                    ->label(__('resources.tickets.form.created_by'))
                    ->sortable()
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('start_date')
                    ->label(__('resources.tickets.form.start_date'))
                    ->date()
                    ->sortable(),

                TextColumn::make('due_date')
                    ->label(__('resources.tickets.form.due_date'))
                    ->date()
                    ->sortable(),
                    
                TextColumn::make('epic.name')
                    ->label(__('resources.tickets.form.epic'))
                    ->sortable()
                    ->searchable()
                    ->default('—')
                    ->placeholder(__('resources.tickets.placeholders.no_epic')),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('project_id')
                    ->label(__('resources.tickets.form.project'))
                    ->options(function () {
                        if (auth()->user()->hasRole(['super_admin'])) {
                            return Project::pluck('name', 'id')->toArray();
                        }

                        return auth()->user()->projects()->pluck('name', 'projects.id')->toArray();
                    })
                    ->searchable()
                    ->preload(),
            
                SelectFilter::make('ticket_status_id')
                    ->label(__('resources.tickets.form.status'))
                    ->options(function () {
                        $projectId = request()->input('tableFilters.project_id');

                        if (!$projectId) {
                            return [];
                        }

                        return TicketStatus::where('project_id', $projectId)
                            ->pluck('name', 'id')
                            ->toArray();
                    })
                    ->searchable()
                    ->preload(),
                    
                SelectFilter::make('epic_id')
                    ->label(__('resources.tickets.form.epic'))
                    ->options(function () {
                        $projectId = request()->input('tableFilters.project_id');

                        if (!$projectId) {
                            return [];
                        }

                        return Epic::where('project_id', $projectId)
                            ->pluck('name', 'id')
                            ->toArray();
                    })
                    ->searchable()
                    ->preload(),

                SelectFilter::make('priority_id')
                    ->label(__('resources.tickets.form.priority'))
                    ->options(TicketPriority::pluck('name', 'id')->toArray())
                    ->searchable()
                    ->preload(),

                // Filter by assignees
                SelectFilter::make('assignees')
                    ->label(__('resources.tickets.filters.assignee'))
                    ->relationship('assignees', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload(),

                // Filter by creator
                SelectFilter::make('created_by')
                    ->label(__('resources.tickets.form.created_by'))
                    ->relationship('creator', 'name')
                    ->searchable()
                    ->preload(),
            
                Filter::make('due_date')
                    ->schema([
                        DatePicker::make('due_from')
                            ->label(__('due from')),
                        DatePicker::make('due_until')
                            ->label(__('due until')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['due_from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('due_date', '>=', $date),
                            )
                            ->when(
                                $data['due_until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('due_date', '<=', $date),
                            );
                    })
                    ->columns(2),
            ],Tables\Enums\FiltersLayout::AboveContent)
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('download_file')
                    ->iconButton()
                    ->icon('heroicon-c-folder-arrow-down')
                    ->visible(fn(Ticket $record): bool => !empty($record->file))
                    ->action(function ($record) {
                        return response()->download(storage_path('app/public/' . $record->file));
                    }),
                ViewAction::make(),
                EditAction::make(),
                Action::make('copy')
                    ->label(__('resources.tickets.actions.copy'))
                    ->icon('heroicon-o-document-duplicate')
                    ->color('info')
                    ->action(function ($record, $livewire) {
                        // Redirect ke halaman create, dengan parameter copy_from
                        return $livewire->redirect(
                            static::getUrl('create', [
                                'copy_from' => $record->id,
                            ])
                        );
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(auth()->user()->hasRole(['super_admin'])),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [

        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTickets::route('/'),
            'create' => CreateTicket::route('/create'),
            'view' => ViewTicket::route('/{record}'),
            'edit' => EditTicket::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $query = static::getEloquentQuery();

        return $query->count();
    }
}

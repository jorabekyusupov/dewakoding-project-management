<?php

namespace App\Filament\Resources;

use App\Filament\Actions\ImportTicketsAction;
use App\Filament\Resources\ProjectResource\Pages;
use App\Filament\Resources\ProjectResource\RelationManagers;
use App\Models\Project;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = null;
    protected static ?int $navigationSort = 1;


    /**
     * @return string|null
     */
    public static function getNavigationLabel(): string
    {
        return __('projects');
    }
    /**
     * @return string|null
     */
    public static function getLabel(): ?string
    {
        return __('project');
    }

    /**
     * @return string|null
     */
    public static function getPluralLabel(): ?string
    {
        return __('projects');
    }


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\RichEditor::make('description')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('ticket_prefix')
                    ->required()
                    ->maxLength(255),
                Forms\Components\DatePicker::make('start_date')
                    ->label(__('resources.projects.form.start_date'))
                    ->native(false)
                    ->displayFormat('d/m/Y'),
                Forms\Components\DatePicker::make('end_date')
                    ->label(__('resources.projects.form.end_date'))
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->afterOrEqual('start_date'),
                Forms\Components\Toggle::make('create_default_statuses')
                    ->label(__('resources.projects.form.use_default_statuses'))
                    ->helperText(__('resources.projects.form.use_default_statuses_help'))
                    ->default(true)
                    ->dehydrated(false)
                    ->visible(fn ($livewire) => $livewire instanceof Pages\CreateProject),
                
                Forms\Components\Toggle::make('is_pinned')
                    ->label(__('resources.projects.form.pin_project'))
                    ->helperText(__('resources.projects.form.pin_project_help'))
                    ->live()
                    ->afterStateUpdated(function ($state, $set) {
                        if ($state) {
                            $set('pinned_date', now());
                        } else {
                            $set('pinned_date', null);
                        }
                    })
                    ->dehydrated(false)
                    ->afterStateHydrated(function ($component, $state, $get) {
                        $component->state(!is_null($get('pinned_date')));
                    }),
                Forms\Components\DateTimePicker::make('pinned_date')
                    ->label(__('resources.projects.form.pinned_date'))
                    ->native(false)
                    ->displayFormat('d/m/Y H:i')
                    ->visible(fn ($get) => $get('is_pinned'))
                    ->dehydrated(true),
                Forms\Components\FileUpload::make('file')
                    ->label(__('file'))
                    ->columnSpanFull()
                    ->disk('public')
                    ->directory('project_files')
                    ->getUploadedFileNameForStorageUsing(
                        fn(TemporaryUploadedFile $file): string => (string)str($file->getClientOriginalName())
                            ->prepend('project-file-'),
                    ),
                Forms\Components\TextInput::make('chat_id')
                    ->label(__('resources.projects.form.chat_id'))
                    ->helperText(__('resources.projects.form.chat_id_help'))
                    ->maxLength(255),
                Forms\Components\TextInput::make('thread_id')
                    ->label(__('resources.projects.form.thread_id'))
                    ->helperText(__('resources.projects.form.thread_id_help'))
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('ticket_prefix')
                    ->searchable(),
                Tables\Columns\TextColumn::make('progress_percentage')
                    ->label(__('resources.projects.columns.progress'))
                    ->getStateUsing(function (Project $record): string {
                        return $record->progress_percentage . '%';
                    })
                    ->badge()
                    ->color(fn (Project $record): string => 
                        $record->progress_percentage >= 100 ? 'success' :
                        ($record->progress_percentage >= 75 ? 'info' :
                        ($record->progress_percentage >= 50 ? 'warning' :
                        ($record->progress_percentage >= 25 ? 'gray' : 'danger')))
                    )
                    ->sortable(),
                Tables\Columns\TextColumn::make('start_date')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_date')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('remaining_days')
                    ->label(__('resources.projects.columns.remaining_days'))
                    ->getStateUsing(function (Project $record): ?string {
                        if (!$record->end_date) {
                            return null;
                        }
                        
                        return __('resources.projects.columns.remaining_days_value', ['count' => $record->remaining_days]);
                    })
                    ->badge()
                    ->color(fn (Project $record): string => 
                        !$record->end_date ? 'gray' :
                        ($record->remaining_days <= 0 ? 'danger' : 
                        ($record->remaining_days <= 7 ? 'warning' : 'success'))
                    ),
                Tables\Columns\ToggleColumn::make('is_pinned')
                    ->label(__('resources.projects.columns.pinned'))
                    ->updateStateUsing(function ($record, $state) {
                        // Gunakan method pin/unpin yang sudah ada di model
                        if ($state) {
                            $record->pin();
                        } else {
                            $record->unpin();
                        }
                        return $state;
                    }),
                Tables\Columns\TextColumn::make('members_count')
                    ->counts('members')
                    ->label(__('resources.projects.columns.members')),
                Tables\Columns\TextColumn::make('tickets_count')
                    ->counts('tickets')
                    ->label(__('resources.projects.columns.tickets')),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\TicketStatusesRelationManager::class,
            RelationManagers\MembersRelationManager::class,
            RelationManagers\EpicsRelationManager::class,
            RelationManagers\TicketsRelationManager::class,
            RelationManagers\NotesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProjects::route('/'),
            'create' => Pages\CreateProject::route('/create'),
            'view' => Pages\ViewProject::route('/{record}'),
            'edit' => Pages\EditProject::route('/{record}/edit'),
            // Hapus baris ini: 'gantt-chart' => Pages\ProjectGanttChart::route('/gantt-chart'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $userIsSuperAdmin = auth()->user() && (
            (method_exists(auth()->user(), 'hasRole') && auth()->user()->hasRole('super_admin'))
            || (isset(auth()->user()->role) && auth()->user()->role === 'super_admin')
        );

        if (! $userIsSuperAdmin) {
            $query->whereHas('members', function (Builder $query) {
                $query->where('user_id', auth()->id());
            });
        }

        return $query;
    }
}

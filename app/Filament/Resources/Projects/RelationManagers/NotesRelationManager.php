<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Hidden;
use Filament\Tables\Filters\Filter;
use Filament\Actions\CreateAction;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Models\ProjectNote;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\DatePicker;
use Filament\Actions\StaticAction;
use Illuminate\Database\Eloquent\Model;

class NotesRelationManager extends RelationManager
{
    protected static string $relationship = 'notes';

    protected static ?string $title = null;

    protected static ?string $modelLabel = null;

    protected static ?string $pluralModelLabel = null;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('resources.project.notes.title');
    }

    public static function getModelLabel(): string
    {
        return __('resources.project.notes.labels.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources.project.notes.labels.plural');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                
                DatePicker::make('note_date')
                    ->label(__('resources.project.notes.fields.note_date'))
                    ->default(now())
                    ->required(),

                RichEditor::make('content')
                    ->required()
                    ->columnSpanFull()
                    ->toolbarButtons([
                        'attachFiles',
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
                    ->helperText(__('resources.project.notes.fields.content_help')),
                
                Hidden::make('created_by')
                    ->default(auth()->id()),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Medium),
                
                TextColumn::make('note_date')
                    ->date('M d, Y')
                    ->sortable(),

                TextColumn::make('creator.name')
                    ->label(__('resources.project.notes.columns.created_by'))
                    ->sortable(),

                TextColumn::make('created_at')
                    ->dateTime('M d, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('recent')
                    ->query(fn ($query) => $query->where('created_at', '>=', now()->subDays(30)))
                    ->label(__('resources.project.notes.filters.recent')),
            ])
            ->headerActions([
                CreateAction::make()
                    ->icon('heroicon-o-plus')
                    ->label(__('resources.project.notes.actions.add'))
                    ->modalWidth('2xl')
                    ->closeModalByClickingAway(false)
                    ,
            ])
            ->recordActions([
                ViewAction::make()
                    ->closeModalByClickingAway(false),
                EditAction::make()
                    ->closeModalByClickingAway(false),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('note_date', 'desc')
            ->emptyStateHeading(__('resources.project.notes.empty.heading'))
            ->emptyStateDescription(__('resources.project.notes.empty.description'))
            ->emptyStateIcon('heroicon-o-document-text');
    }
}

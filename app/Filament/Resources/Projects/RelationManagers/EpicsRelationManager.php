<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class EpicsRelationManager extends RelationManager
{
    protected static string $relationship = 'epics';

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return $ownerRecord->epics_count ?? $ownerRecord->epics()->count();
    }
    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('epics');
    }
    public function form(Schema $schema): Schema
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->label(__('resources.project.epics.form.name')),
                TextInput::make('sort_order')
                    ->numeric()
                    ->default(0)
                    ->label(__('resources.project.epics.form.sort_order'))
                    ->helperText(__('resources.project.epics.form.sort_order_help')),
                DatePicker::make('start_date')
                    ->label(__('resources.project.epics.form.start_date'))
                    ->nullable(),
                DatePicker::make('end_date')
                    ->label(__('resources.project.epics.form.end_date'))
                    ->nullable(),
                RichEditor::make('description')
                    ->columnSpanFull()
                    ->nullable(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('sort_order')
                    ->label(__('resources.project.epics.columns.sort_order'))
                    ->label('Order')
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('start_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('end_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('tickets_count')
                    ->counts('tickets')
                    ->label(__('resources.project.epics.columns.tickets')),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order', 'asc');
    }
}

<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
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
use App\Models\TicketStatus;
use Filament\Notifications\Notification;

class TicketStatusesRelationManager extends RelationManager
{
    protected static string $relationship = 'ticketStatuses';


    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('project-statuses');
    }
    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return $ownerRecord->ticket_statuses_count ?? $ownerRecord->ticketStatuses()->count();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('resources.project.statuses.form.name'))
                    ->required()
                    ->maxLength(255),
                ColorPicker::make('color')
                    ->label(__('resources.project.statuses.form.color'))
                    ->required()
                    ->default('#3490dc')
                    ->helperText(__('resources.project.statuses.form.color_help')),
                TextInput::make('sort_order')
                    ->numeric()
                    ->default(0)
                    ->label(__('resources.project.statuses.form.sort_order'))
                    ->helperText(__('resources.project.statuses.form.sort_order_help')),
                Toggle::make('is_completed')
                    ->label(__('resources.project.statuses.form.is_completed'))
                    ->helperText(__('resources.project.statuses.form.is_completed_help'))
                    ->default(false)
                    ->reactive()
                    ->afterStateUpdated(function ($state, $get, $set, $record) {
                        if ($state) {
                            // Check if another status in this project is already marked as completed
                            $projectId = $this->getOwnerRecord()->id;
                            $existingCompleted = TicketStatus::where('project_id', $projectId)
                                ->where('is_completed', true)
                                ->when($record, fn($query) => $query->where('id', '!=', $record->id))
                                ->first();
                            
                                if ($existingCompleted) {
                                    $set('is_completed', false);
                                    Notification::make()
                                        ->warning()
                                        ->title(__('resources.project.statuses.notifications.cannot_mark'))
                                        ->body(__('resources.project.statuses.notifications.completed_exists_detailed', ['status' => $existingCompleted->name]))
                                        ->send();
                                }
                        }
                    }),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('resources.project.statuses.columns.name')),
                ColorColumn::make('color')
                    ->label(__('resources.project.statuses.columns.color')),
                TextColumn::make('sort_order')
                    ->label(__('resources.project.statuses.columns.sort_order')),
                IconColumn::make('is_completed')
                    ->label(__('resources.project.statuses.columns.completed'))
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray'),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateDataUsing(function (array $data): array {
                        $maxOrder = $this->getRelationship()->max('sort_order') ?? -1;
                        $data['sort_order'] = $maxOrder + 1;
                        
                        // Additional validation for is_completed
                        if ($data['is_completed'] ?? false) {
                            $projectId = $this->getOwnerRecord()->id;
                            $existingCompleted = TicketStatus::where('project_id', $projectId)
                                ->where('is_completed', true)
                                ->first();
                            
                            if ($existingCompleted) {
                                $data['is_completed'] = false;
                                Notification::make()
                                    ->warning()
                                    ->title(__('resources.project.statuses.notifications.cannot_mark'))
                                    ->body(__('resources.project.statuses.notifications.completed_exists', ['status' => $existingCompleted->name]))
                                    ->send();
                            }
                        }
                        
                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateDataUsing(function (array $data, Model $record): array {
                        // Additional validation for is_completed on edit
                        if ($data['is_completed'] ?? false) {
                            $projectId = $this->getOwnerRecord()->id;
                            $existingCompleted = TicketStatus::where('project_id', $projectId)
                                ->where('is_completed', true)
                                ->where('id', '!=', $record->id)
                                ->first();
                            
                            if ($existingCompleted) {
                                $data['is_completed'] = false;
                                Notification::make()
                                    ->warning()
                                    ->title(__('resources.project.statuses.notifications.cannot_mark'))
                                    ->body(__('resources.project.statuses.notifications.completed_exists', ['status' => $existingCompleted->name]))
                                    ->send();
                            }
                        }
                        
                        return $data;
                    }),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

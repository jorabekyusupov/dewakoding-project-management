<?php

namespace App\Filament\Resources\ProjectResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
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

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label(__('resources.project.statuses.form.name'))
                    ->required()
                    ->maxLength(255),
                Forms\Components\ColorPicker::make('color')
                    ->label(__('resources.project.statuses.form.color'))
                    ->required()
                    ->default('#3490dc')
                    ->helperText(__('resources.project.statuses.form.color_help')),
                Forms\Components\TextInput::make('sort_order')
                    ->numeric()
                    ->default(0)
                    ->label(__('resources.project.statuses.form.sort_order'))
                    ->helperText(__('resources.project.statuses.form.sort_order_help')),
                Forms\Components\Toggle::make('is_completed')
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
                Tables\Columns\TextColumn::make('name')
                    ->label(__('resources.project.statuses.columns.name')),
                Tables\Columns\ColorColumn::make('color')
                    ->label(__('resources.project.statuses.columns.color')),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label(__('resources.project.statuses.columns.sort_order')),
                Tables\Columns\IconColumn::make('is_completed')
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
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
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
            ->actions([
                Tables\Actions\EditAction::make()
                    ->mutateFormDataUsing(function (array $data, Model $record): array {
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
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}

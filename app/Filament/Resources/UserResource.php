<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = null;

    public static function getNavigationLabel(): string
    {
        return __('resources.users.navigation_label');
    }

    /**
     * @return string|null
     */
    public static function getNavigationGroup(): ?string
    {
        return __('navigation.settings');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required()
                    ->unique(
                        ignoreRecord: true 
                    )
                    ->maxLength(255),
                Forms\Components\DateTimePicker::make('email_verified_at'),
                Forms\Components\TextInput::make('password')
                    ->password()
                    ->dehydrateStateUsing(fn ($state) => ! empty($state) ? Hash::make($state) : null
                    )
                    ->dehydrated(fn ($state) => ! empty($state))
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->maxLength(255),
                Forms\Components\Select::make('roles')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable(),
                Forms\Components\TextInput::make('chat_id')
                    ->label(__('resources.projects.form.chat_id'))
                    ->maxLength(255)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('roles.name')
                    ->label(__('resources.users.columns.roles'))
                    ->badge()
                    ->separator(',')
                    ->tooltip(fn (User $record): string => $record->roles->pluck('name')->join(', ') ?: __('resources.users.tooltips.no_roles'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('projects_count')
                    ->label(__('resources.users.columns.projects'))
                    ->counts('projects')
                    ->tooltip(fn (User $record): string => $record->projects->pluck('name')->join(', ') ?: __('resources.users.tooltips.no_projects'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('assigned_tickets_count')
                    ->label(__('resources.users.columns.assigned_tickets'))
                    ->counts('assignedTickets')
                    ->tooltip(__('resources.users.tooltips.assigned_count'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_tickets_count')
                    ->label(__('resources.users.columns.created_tickets'))
                    ->getStateUsing(function (User $record): int {
                        return $record->createdTickets()->count();
                    })
                    ->tooltip(__('resources.users.tooltips.created_count'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('email_verified_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('has_projects')
                    ->label(__('resources.users.filters.has_projects'))
                    ->query(fn (Builder $query): Builder => $query->whereHas('projects')),

                Tables\Filters\Filter::make('has_assigned_tickets')
                    ->label(__('resources.users.filters.has_assigned_tickets'))
                    ->query(fn (Builder $query): Builder => $query->whereHas('assignedTickets')),

                Tables\Filters\Filter::make('has_created_tickets')
                    ->label(__('resources.users.filters.has_created_tickets'))
                    ->query(fn (Builder $query): Builder => $query->whereHas('createdTickets')),

                // Filter by role
                Tables\Filters\SelectFilter::make('roles')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('email_unverified')
                    ->label(__('resources.users.filters.email_unverified'))
                    ->query(fn (Builder $query): Builder => $query->whereNull('email_verified_at')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    
                    // NEW: Bulk action to assign role
                    Tables\Actions\BulkAction::make('assignRole')
                        ->label(__('resources.users.actions.assign_role'))
                        ->icon('heroicon-o-shield-check')
                        ->form([
                            Forms\Components\Select::make('roles')
                                ->label(__('resources.users.actions.assign_role.roles'))
                                ->relationship('roles', 'name')
                                ->multiple()
                                ->preload()
                                ->searchable()
                                ->required(),
                            
                            Forms\Components\Radio::make('role_mode')
                                ->label(__('resources.users.actions.assign_role.mode'))
                                ->options([
                                    'replace' => __('resources.users.actions.assign_role.replace'),
                                    'add' => __('resources.users.actions.assign_role.add'),
                                ])
                                ->default('add')
                                ->required(),
                        ])
                        ->action(function (array $data, $records) {
                            foreach ($records as $record) {
                                if ($data['role_mode'] === 'replace') {
                                    $record->roles()->sync($data['roles']);
                                } else {
                                    $record->roles()->syncWithoutDetaching($data['roles']);
                                }
                            }
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ProjectsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit')
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }
}

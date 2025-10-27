<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NotificationResource\Pages;
use App\Models\Notification;
use App\Services\NotificationService;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Database\Eloquent\Builder;

class NotificationResource extends Resource
{
    protected static ?string $model = Notification::class;

    protected static ?string $navigationIcon = 'heroicon-o-bell';
    
    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return __('resources.notifications.navigation_label');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(fn () => 
                // Show all notifications for super_admin, only user's notifications for others
                auth()->user()->hasRole('super_admin') 
                    ? Notification::with(['user', 'ticket.project'])
                    : Notification::where('user_id', auth()->id())->with(['ticket.project'])
            )
            ->columns([
                Tables\Columns\IconColumn::make('read_status')
                    ->label('')
                    ->icon(fn (Notification $record) => $record->isUnread() ? 'heroicon-o-bell' : 'heroicon-o-bell-slash')
                    ->color(fn (Notification $record) => $record->isUnread() ? 'warning' : 'gray')
                    ->size('sm'),
                    
                // Add user column for super_admin
                Tables\Columns\TextColumn::make('user.name')
                    ->label(__('resources.notifications.columns.user'))
                    ->badge()
                    ->color('info')
                    ->searchable()
                    ->visible(fn () => auth()->user()->hasRole('super_admin')),
                    
                Tables\Columns\TextColumn::make('message')
                    ->limit(50)
                    ->weight(fn (Notification $record) => $record->isUnread() ? 'bold' : 'normal'),

                Tables\Columns\TextColumn::make('ticket.name')
                    ->label(__('resources.notifications.columns.ticket'))
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->placeholder(__('resources.notifications.placeholder.na')),
                    
                Tables\Columns\TextColumn::make('ticket.project.name')
                    ->label(__('resources.notifications.columns.project'))
                    ->badge()
                    ->color('success')
                    ->searchable()
                    ->placeholder(__('resources.notifications.placeholder.na')),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Action::make('markAsRead')
                    ->label(__('resources.notifications.actions.mark_as_read'))
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (Notification $record) => $record->isUnread() && (auth()->id() === $record->user_id || auth()->user()->hasRole('super_admin')))
                    ->action(function (Notification $record) {
                        app(NotificationService::class)->markAsRead($record->id, $record->user_id);
                        
                        FilamentNotification::make()
                            ->title(__('resources.notifications.messages.marked_as_read'))
                            ->success()
                            ->send();
                    }),
                    
                Action::make('viewTicket')
                    ->label(__('resources.notifications.actions.view_ticket'))
                    ->icon('heroicon-o-eye')
                    ->color('primary')
                    ->visible(fn (Notification $record) => isset($record->data['ticket_id']))
                    ->url(fn (Notification $record) => 
                        route('filament.admin.resources.tickets.view', ['record' => $record->data['ticket_id']])
                    )
                    ->openUrlInNewTab(),
            ])
            ->headerActions([
                Action::make('markAllAsRead')
                    ->label(__('resources.notifications.actions.mark_all'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn () => !auth()->user()->hasRole('super_admin')) // Only show for non-super_admin
                    ->action(function () {
                        app(NotificationService::class)->markAllAsRead(auth()->id());
                        
                        FilamentNotification::make()
                            ->title(__('resources.notifications.messages.marked_all'))
                            ->success()
                            ->send();
                    }),
            ])
            ->filters([
                Tables\Filters\Filter::make('unread')
                    ->label(__('resources.notifications.filters.unread_only'))
                    ->query(fn (Builder $query) => $query->unread()),
                    
                Tables\Filters\SelectFilter::make('user')
                    ->label(__('resources.notifications.columns.user'))
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(fn () => auth()->user()->hasRole('super_admin')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNotifications::route('/'),
        ];
    }
    
    public static function canCreate(): bool
    {
        return false;
    }
    
    public static function getNavigationBadge(): ?string
    {
        return auth()->user()?->unreadNotifications()->count() ?: null;
    }
    
    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }
}

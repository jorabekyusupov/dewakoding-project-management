<?php

namespace App\Filament\Widgets;

use App\Models\UserAuthLog;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class UserAuthLogTable extends BaseWidget
{
    use HasWidgetShield;

    protected static ?string $heading = null;

    protected int | string | array $columnSpan = [
        'md' => 2,
        'xl' => 1,
    ];

    protected static ?int $sort = 8;

    protected function getHeading(): ?string
    {
        return __('widgets.auth_logs.heading');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                UserAuthLog::query()
                    ->with('user')
                    ->when(! auth()->user()->hasRole('super_admin'), function ($query) {
                        $query->where('user_id', auth()->id());
                    })
                    ->latest('login_at')
            )
            ->columns([
                TextColumn::make('summary')
                    ->label(__('widgets.auth_logs.columns.activity'))
                    ->state(function (UserAuthLog $record): string {
                        $userName = $record->user->name ?? __('widgets.auth_logs.unknown_user');
                        $ipAddress = $record->ip_address ?? __('widgets.auth_logs.unknown_ip');
                        $action = $record->logout_at
                            ? __('widgets.auth_logs.activity_logout')
                            : __('widgets.auth_logs.activity_login');

                        return __(
                            'widgets.auth_logs.summary_html',
                            [
                                'user' => e($userName),
                                'action' => $action,
                                'ip' => e($ipAddress),
                            ]
                        );
                    })
                    ->description(function (UserAuthLog $record): string {
                        $login = $this->formatTimestamp($record->login_at)
                            ?? __('widgets.auth_logs.labels.no_login');

                        $logout = $this->formatTimestamp($record->logout_at)
                            ?? __('widgets.auth_logs.labels.active_session');

                        $agent = Str::limit($record->user_agent ?? __('widgets.auth_logs.unknown_agent'), 60);

                        return __('widgets.auth_logs.metadata', [
                            'login' => $login,
                            'logout' => $logout,
                            'agent' => $agent,
                        ]);
                    })
                    ->html()
                    ->searchable(['users.name', 'ip_address', 'user_agent'])
                    ->weight('medium'),
                TextColumn::make('login_at')
                    ->label(__('widgets.auth_logs.columns.login_at'))
                    ->dateTime('M d, H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('logout_at')
                    ->label(__('widgets.auth_logs.columns.logout_at'))
                    ->dateTime('M d, H:i')
                    ->placeholder(__('widgets.auth_logs.labels.active_session'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('ip_address')
                    ->label(__('widgets.auth_logs.columns.ip'))
                    ->copyable()
                    ->copyMessage(__('widgets.auth_logs.actions.copied_ip'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('login_at', 'desc')
            ->filters([
                Filter::make('date_range')
                    ->label(__('widgets.auth_logs.filters.date_range'))
                    ->form([
                        DatePicker::make('start_date')
                            ->label(__('resources.tickets.form.start_date')),
                        DatePicker::make('end_date')
                            ->label(__('resources.tickets.form.end_date')),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['start_date'], function ($query, $date) {
                                $query->whereDate('login_at', '>=', $date);
                            })
                            ->when($data['end_date'], function ($query, $date) {
                                $query->whereDate('login_at', '<=', $date);
                            });
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['start_date'] ?? null) {
                            $indicators[] = __(
                                'widgets.auth_logs.filters.indicator_from',
                                ['date' => Carbon::parse($data['start_date'])->format('M d, Y')]
                            );
                        }

                        if ($data['end_date'] ?? null) {
                            $indicators[] = __(
                                'widgets.auth_logs.filters.indicator_to',
                                ['date' => Carbon::parse($data['end_date'])->format('M d, Y')]
                            );
                        }

                        return $indicators;
                    }),
                Filter::make('today')
                    ->label(__('widgets.auth_logs.filters.today'))
                    ->query(fn ($query) => $query->whereDate('login_at', today()))
                    ->toggle(),
                SelectFilter::make('user_id')
                    ->label(__('widgets.auth_logs.filters.user'))
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->filtersFormColumns(2)
            ->recordActions([
                Action::make('open_user')
                    ->label('')
                    ->icon('heroicon-o-user-circle')
                    ->size('sm')
                    ->visible(fn (UserAuthLog $record): bool => filled($record->user))
                    ->tooltip(__('widgets.auth_logs.actions.open_user'))
                    ->url(fn (UserAuthLog $record): string => route('filament.admin.resources.users.edit', $record->user))
                    ->openUrlInNewTab(),
            ])
            ->paginated([5, 25, 50])
            ->poll('30s')
            ->striped()
            ->emptyStateHeading(__('widgets.auth_logs.empty.heading'))
            ->emptyStateDescription(__('widgets.auth_logs.empty.description'))
            ->emptyStateIcon('heroicon-o-lock-closed');
    }

    private function formatTimestamp(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->timezone(config('app.timezone'))->format('M d, H:i');
        } catch (\Throwable) {
            return null;
        }
    }
}

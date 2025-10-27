<?php

namespace App\Filament\Widgets;

use App\Models\Ticket;
use Filament\Widgets\ChartWidget;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MonthlyTicketTrendChart extends ChartWidget
{
    use HasWidgetShield;

    protected static ?string $heading = null;

    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = [
        'md' => 2,
        'xl' => 2,
    ];

    protected static ?string $maxHeight = '300px';

    protected static ?string $pollingInterval = '60s';

    public function getHeading(): ?string
    {
        return __('widgets.monthly_ticket_trend.heading');
    }

    public function getData(): array
    {
        $user = auth()->user();
        $isSuperAdmin = $user->hasRole('super_admin');

        // Get the earliest ticket date
        $earliestTicketQuery = Ticket::query();

        if (!$isSuperAdmin) {
            $earliestTicketQuery->whereHas('project.members', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            });
        }

        $earliestTicket = $earliestTicketQuery->orderBy('created_at', 'asc')->first();

        if (!$earliestTicket) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        // Calculate months from earliest ticket to now
        $startDate = Carbon::parse($earliestTicket->created_at)->startOfMonth();
        $endDate = Carbon::now()->endOfMonth();

        $months = collect();
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            $months->push($currentDate->copy());
            $currentDate->addMonth();
        }

        $labels = $months->map(function ($month) {
            return $month->format('M Y');
        })->toArray();

        // Query tickets created per month
        $driver = DB::getDriverName();

        $getYear = $driver === 'pgsql' ? 'EXTRACT(YEAR FROM created_at)' : 'YEAR(created_at)';
        $getMonth = $driver === 'pgsql' ? 'EXTRACT(MONTH FROM created_at)' : 'MONTH(created_at)';

        $yearKey = $driver === 'pgsql' ? 'EXTRACT(YEAR FROM created_at)' : 'year';
        $monthKey = $driver === 'pgsql' ? 'EXTRACT(MONTH FROM created_at)' : 'month';

        $ticketsQuery = Ticket::query()
            ->select(
                DB::raw("$getYear AS year"),
                DB::raw("$getMonth AS month"),
                DB::raw('COUNT(*) AS total')
            )
            ->where('created_at', '>=', $startDate)
            ->groupByRaw("$yearKey, $monthKey")
            ->orderByRaw("$yearKey, $monthKey");

        if (!$isSuperAdmin) {
            $ticketsQuery->whereHas('project.members', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            });
        }

        $ticketData = $ticketsQuery->get()->keyBy(function ($item) {
            return $item->year . '-' . str_pad($item->month, 2, '0', STR_PAD_LEFT);
        });

        // Fill data for each month
        $data = $months->map(function ($month) use ($ticketData) {
            $key = $month->format('Y-m');
            return $ticketData->get($key)->total ?? 0;
        })->toArray();

        return [
            'datasets' => [
                [
                    'label' => __('widgets.monthly_ticket_trend.dataset.tickets_created'),
                    'data' => $data,
                    'borderColor' => '#3B82F6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'borderWidth' => 2,
                    'fill' => true,
                    'tension' => 0.4,
                ],
            ],
            'labels' => $labels,
        ];
    }

    public function getType(): string
    {
        return 'line';
    }

    public function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'top',
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'stepSize' => 1,
                    ],
                ],
            ],
            'responsive' => true,
            'maintainAspectRatio' => false,
        ];
    }
}

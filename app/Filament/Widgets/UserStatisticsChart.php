<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\ChartWidget;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;

class UserStatisticsChart extends ChartWidget
{
    use HasWidgetShield;
    
    protected static ?string $heading = null;
    
    protected int | string | array $columnSpan = [
        'md' => 2,
        'xl' => 1,
    ];
    
    protected static ?int $sort = 3;
    
    protected static ?string $maxHeight = '300px';
    
    protected static ?string $pollingInterval = '30s';

    public function getHeading(): ?string
    {
        return __('widgets.user_statistics.heading');
    }
    
    public function getData(): array
    {
        $users = User::query()
            ->when(!auth()->user()->hasRole('super_admin'), function ($query) {
                $query->where('id', auth()->id());
            })
            ->withCount([
                'projects as total_projects',
                'assignedTickets as total_assigned_tickets'
            ])
            ->orderBy('name')
            ->get();
        
        $labels = $users->pluck('name')->toArray();
        $projectsData = $users->pluck('total_projects')->toArray();
        $ticketsData = $users->pluck('total_assigned_tickets')->toArray();
        
        return [
            'datasets' => [
                [
                    'label' => __('widgets.user_statistics.dataset.total_projects'),
                    'data' => $projectsData,
                    'backgroundColor' => '#3B82F6',
                    'borderColor' => '#3B82F6',
                    'borderWidth' => 1,
                ],
                [
                    'label' => __('widgets.user_statistics.dataset.total_assigned_tickets'),
                    'data' => $ticketsData,
                    'backgroundColor' => '#10B981',
                    'borderColor' => '#10B981',
                    'borderWidth' => 1,
                ],
            ],
            'labels' => $labels,
        ];
    }
    
    public function getType(): string
    {
        return 'bar';
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

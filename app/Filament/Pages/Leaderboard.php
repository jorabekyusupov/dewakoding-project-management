<?php

namespace App\Filament\Pages;

use Exception;
use Log;
use App\Models\User;
use App\Models\Ticket;
use App\Models\TicketHistory;
use App\Models\TicketComment;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;

class Leaderboard extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-trophy';
    protected static ?string $navigationLabel = null;
    protected static ?string $title = null;
    protected static ?int $navigationSort = 6;
    protected  string $view = 'filament.pages.leaderboard';
    protected static  string | \UnitEnum | null $navigationGroup = 'Analytics';
    protected static ?string $slug = 'leaderboard';

    public static function getNavigationLabel(): string
    {
        return __('pages.leaderboard.navigation_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.analytics');
    }

    public function getTitle(): string
    {
        return __('pages.leaderboard.title');
    }

    public string $timeRange = '7days'; // Changed from 'thisweek' to '7days'
    public int $topCount = 10; // Number of top contributors to show

    public function getSubheading(): ?string
    {
        return __('pages.leaderboard.subheading');
    }

    public function setTimeRange(string $range): void
    {
        $this->timeRange = $range;
    }

    public function setTopCount(int $count): void
    {
        $this->topCount = $count;
    }

    public function getLeaderboardData(): array
    {
        $users = User::orderBy('name')->get();
        $leaderboardData = [];

        foreach ($users as $user) {
            $stats = $this->getUserStats($user->id);
            $totalScore = $this->calculateContributionScore($stats);

            if ($totalScore > 0) { // Only include users with contributions
                $leaderboardData[] = [
                    'user' => $user,
                    'stats' => $stats,
                    'total_score' => $totalScore,
                    'rank' => 0 // Will be set after sorting
                ];
            }
        }

        // Sort by total score descending
        usort($leaderboardData, function($a, $b) {
            return $b['total_score'] <=> $a['total_score'];
        });

        // Assign ranks
        foreach ($leaderboardData as $index => &$data) {
            $data['rank'] = $index + 1;
        }

        // Return only top contributors
        return array_slice($leaderboardData, 0, $this->topCount);
    }

    private function calculateContributionScore(array $stats): int
    {

        // Updated weighted scoring system
        $weights = [
            'tickets_created' => 2,    // Updated: Tickets created = 2 points
            'status_changes' => 5,     // Updated: Status changes = 5 points
            'comments_made' => 2,      // Comments remain 2 points
            'active_days' => 1,
            'regression_penalty' => 5   // Penalty per regression
        ];

        $regressionPenalty = $stats['regression_penalty'] ?? (($stats['status_regressions'] ?? 0) * 2);

        return (
            ($stats['tickets_created'] * $weights['tickets_created']) +
            ($stats['status_changes'] * $weights['status_changes']) +
            ($stats['comments_made'] * $weights['comments_made']) +
            ($stats['active_days'] * $weights['active_days']) -
            $regressionPenalty
        );
    }

    private function calculateStatusRegressions(Collection $historyEntries): int
    {
        if ($historyEntries->isEmpty()) {
            return 0;
        }

        $regressions = 0;

        $historyEntries
            ->groupBy('ticket_id')
            ->each(function (Collection $ticketHistory) use (&$regressions) {
                $previousSortOrder = null;

                foreach ($ticketHistory->sortBy('created_at') as $history) {
                    $currentSortOrder = optional($history->status)->sort_order;

                    if ($currentSortOrder === null) {
                        continue;
                    }

                    if ($previousSortOrder !== null && $currentSortOrder < $previousSortOrder) {
                        $regressions++;
                    }

                    $previousSortOrder = $currentSortOrder;
                }
            });

        return $regressions;
    }

    private function getUserStats(int $userId): array
    {
        $dateRange = $this->getDateRangeFromTimeRange();
        $startDate = $dateRange['start'];
        $endDate = $dateRange['end'];

        try {
            $rangeStart = $startDate->copy()->startOfDay()->utc();
            $rangeEnd = $endDate->copy()->endOfDay()->utc();

            $statusHistory = TicketHistory::with('status:id,sort_order')
                ->where('user_id', $userId)
                ->whereBetween('created_at', [$rangeStart, $rangeEnd])
                ->orderBy('ticket_id')
                ->orderBy('created_at')
                ->get(['id', 'ticket_id', 'user_id', 'ticket_status_id', 'created_at']);

            $rawStatusChanges = $statusHistory->count();
            $statusRegressions = $this->calculateStatusRegressions($statusHistory);
            $statusChangesCount = max($rawStatusChanges - $statusRegressions, 0);

            return [
                'tickets_created' => Ticket::where('created_by', $userId)
                    ->whereBetween('created_at', [$rangeStart, $rangeEnd])
                    ->count(),
                'status_changes' => $statusChangesCount,
                'status_regressions' => $statusRegressions,
                'regression_penalty' => $statusRegressions * 6,
                'comments_made' => TicketComment::where('user_id', $userId)
                    ->whereBetween('created_at', [$rangeStart, $rangeEnd])
                    ->count(),
                'active_days' => $this->getUserActiveDays($userId)
            ];
        } catch (Exception $e) {
            Log::error('Error getting user stats: ' . $e->getMessage());
            return [
                'tickets_created' => 0,
                'status_changes' => 0,
                'status_regressions' => 0,
                'regression_penalty' => 0,
                'comments_made' => 0,
                'active_days' => 0
            ];
        }
    }

    private function getUserActiveDays(int $userId): int
    {
        $dateRange = $this->getDateRangeFromTimeRange();
        $startDate = $dateRange['start'];
        $endDate = $dateRange['end'];

        // Get unique dates where user had activity - simplified approach
        $ticketDates = Ticket::where('created_by', $userId)
            ->whereBetween('created_at', [
                $startDate->startOfDay()->utc(),
                $endDate->endOfDay()->utc()
            ])
            ->selectRaw('DATE(created_at) as activity_date')
            ->distinct()
            ->pluck('activity_date');

        $historyDates = TicketHistory::where('user_id', $userId)
            ->whereBetween('created_at', [
                $startDate->startOfDay()->utc(),
                $endDate->endOfDay()->utc()
            ])
            ->selectRaw('DATE(created_at) as activity_date')
            ->distinct()
            ->pluck('activity_date');

        $commentDates = TicketComment::where('user_id', $userId)
            ->whereBetween('created_at', [
                $startDate->startOfDay()->utc(),
                $endDate->endOfDay()->utc()
            ])
            ->selectRaw('DATE(created_at) as activity_date')
            ->distinct()
            ->pluck('activity_date');

        // Merge and count unique dates
        return $ticketDates->merge($historyDates)
            ->merge($commentDates)
            ->unique()
            ->count();
    }

    private function getDateRangeFromTimeRange(): array
    {
        $endDate = Carbon::now(config('app.timezone'));

        return match($this->timeRange) {
            '7days' => [
                'start' => $endDate->copy()->subDays(6), // 7 days including today
                'end' => $endDate
            ],
            '30days' => [
                'start' => $endDate->copy()->subDays(29), // 30 days including today
                'end' => $endDate
            ],
            'thisweek' => [
                'start' => $endDate->copy()->startOfWeek(),
                'end' => $endDate->copy()->endOfWeek()
            ],
            '1month' => [
                'start' => $endDate->copy()->subDays(29),
                'end' => $endDate
            ],
            default => [
                'start' => $endDate->copy()->subDays(6),
                'end' => $endDate
            ]
        };
    }

    public function getTimeRangeLabel(): string
    {
        return match($this->timeRange) {
            '7days' => __('pages.leaderboard.time_range.7days'),
            '30days' => __('pages.leaderboard.time_range.30days'),
            'thisweek' => __('pages.leaderboard.time_range.thisweek'),
            '1month' => __('pages.leaderboard.time_range.1month'),
            default => __('pages.leaderboard.time_range.7days'),
        };
    }

    public function getRankBadgeColor(int $rank): string
    {
        return match($rank) {
            1 => 'bg-yellow-500 text-white', // Gold
            2 => 'bg-gray-400 text-white',   // Silver
            3 => 'bg-amber-600 text-white',  // Bronze
            default => 'bg-blue-500 text-white'
        };
    }

    public function getRankIcon(int $rank): string
    {
        return match($rank) {
            1 => '🏆',
            2 => '🥈',
            3 => '🥉',
            default => '🏅'
        };
    }
}

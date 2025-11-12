<?php

namespace App\Filament\Widgets;

use App\Models\Project;
use App\Models\Ticket;
use App\Models\User;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class StatsOverview extends BaseWidget
{
    use HasWidgetShield;

    protected ?string $pollingInterval = '30s';

    protected ?string $heading = 'Overview';

    protected function getStats(): array
    {
        $user = auth()->user();
        $isSuperAdmin = $user->hasRole('super_admin');

        if ($isSuperAdmin) {
            return $this->getSuperAdminStats();
        } else {
            return $this->getUserStats();
        }
    }

    public function getSuperAdminStats(): array
    {
        $totalProjects = Project::count();
        $totalTickets = Ticket::count();
        $usersCount = User::count();
        $myTickets = DB::table('tickets')
            ->join('ticket_users', 'tickets.id', '=', 'ticket_users.ticket_id')
            ->where('ticket_users.user_id', auth()->id())
            ->count();

        return [
            Stat::make(__('widgets.stats_overview.cards.total_projects.title'), $totalProjects)
                ->description(__('widgets.stats_overview.cards.total_projects.description'))
                ->descriptionIcon('heroicon-m-rectangle-stack')
                ->color('primary'),

            Stat::make(__('widgets.stats_overview.cards.total_tickets.title'), $totalTickets)
                ->description(__('widgets.stats_overview.cards.total_tickets.description'))
                ->descriptionIcon('heroicon-m-ticket')
                ->color('success'),

            Stat::make(__('widgets.stats_overview.cards.my_assigned_tickets.title'), $myTickets)
                ->description(__('widgets.stats_overview.cards.my_assigned_tickets.description'))
                ->descriptionIcon('heroicon-m-user-circle')
                ->color('info'),

            Stat::make(__('widgets.stats_overview.cards.team_members.title'), $usersCount)
                ->description(__('widgets.stats_overview.cards.team_members.description'))
                ->descriptionIcon('heroicon-m-users')
                ->color('gray'),
        ];
    }

    public function getUserStats(): array
    {
        $user = auth()->user();
        
        $myProjects = $user->projects()->count();
        
        $myProjectIds = $user->projects()->pluck('projects.id')->toArray();

        $projectTickets = Ticket::whereIn('project_id', $myProjectIds)->count();

        $myAssignedTickets = DB::table('tickets')
            ->join('ticket_users', 'tickets.id', '=', 'ticket_users.ticket_id')
            ->where('ticket_users.user_id', $user->id)
            ->count();

        $myCreatedTickets = Ticket::where('created_by', $user->id)->count();

        $newTicketsThisWeek = Ticket::whereIn('project_id', $myProjectIds)
            ->where('tickets.created_at', '>=', Carbon::now()->subDays(7))
            ->count();

        $myOverdueTickets = DB::table('tickets')
            ->join('ticket_users', 'tickets.id', '=', 'ticket_users.ticket_id')
            ->join('ticket_statuses', 'tickets.ticket_status_id', '=', 'ticket_statuses.id')
            ->where('ticket_users.user_id', $user->id)
            ->where('tickets.due_date', '<', Carbon::now())
            ->whereNotIn('ticket_statuses.name', ['Completed', 'Done', 'Closed'])
            ->count();

        $myCompletedThisWeek = DB::table('tickets')
            ->join('ticket_users', 'tickets.id', '=', 'ticket_users.ticket_id')
            ->join('ticket_statuses', 'tickets.ticket_status_id', '=', 'ticket_statuses.id')
            ->where('ticket_users.user_id', $user->id)
            ->whereIn('ticket_statuses.name', ['Completed', 'Done', 'Closed'])
            ->where('tickets.updated_at', '>=', Carbon::now()->subDays(7))
            ->count();

        $teamMembers = User::whereHas('projects', function ($query) use ($myProjectIds) {
            $query->whereIn('projects.id', $myProjectIds);
        })->where('id', '!=', $user->id)->count();

        return [
            Stat::make(__('widgets.stats_overview.cards.my_projects.title'), $myProjects)
                ->description(__('widgets.stats_overview.cards.my_projects.description'))
                ->descriptionIcon('heroicon-m-rectangle-stack')
                ->color('primary'),

            Stat::make(__('widgets.stats_overview.cards.my_assigned_tickets.title'), $myAssignedTickets)
                ->description(__('widgets.stats_overview.cards.my_assigned_tickets.description'))
                ->descriptionIcon('heroicon-m-user-circle')
                ->color($myAssignedTickets > 10 ? 'danger' : ($myAssignedTickets > 5 ? 'warning' : 'success')),

            Stat::make(__('widgets.stats_overview.cards.my_created_tickets.title'), $myCreatedTickets)
                ->description(__('widgets.stats_overview.cards.my_created_tickets.description'))
                ->descriptionIcon('heroicon-m-pencil-square')
                ->color('info'),

            Stat::make(__('widgets.stats_overview.cards.project_tickets.title'), $projectTickets)
                ->description(__('widgets.stats_overview.cards.project_tickets.description'))
                ->descriptionIcon('heroicon-m-ticket')
                ->color('success'),

            Stat::make(__('widgets.stats_overview.cards.completed_this_week.title'), $myCompletedThisWeek)
                ->description(__('widgets.stats_overview.cards.completed_this_week.description'))
                ->descriptionIcon('heroicon-m-check-circle')
                ->color($myCompletedThisWeek > 0 ? 'success' : 'gray'),

            Stat::make(__('widgets.stats_overview.cards.new_tasks_this_week.title'), $newTicketsThisWeek)
                ->description(__('widgets.stats_overview.cards.new_tasks_this_week.description'))
                ->descriptionIcon('heroicon-m-plus-circle')
                ->color('info'),

            Stat::make(__('widgets.stats_overview.cards.my_overdue_tasks.title'), $myOverdueTickets)
                ->description(__('widgets.stats_overview.cards.my_overdue_tasks.description'))
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($myOverdueTickets > 0 ? 'danger' : 'success'),

            Stat::make(__('widgets.stats_overview.cards.team_members.title'), $teamMembers)
                ->description('People in your projects')
                ->descriptionIcon('heroicon-m-users')
                ->color('gray'),
        ];
    }
}
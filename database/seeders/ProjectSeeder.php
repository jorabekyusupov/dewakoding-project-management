<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProjectSeeder extends Seeder
{
    public function run(): void
    {
        DB::unprepared(file_get_contents(database_path('data/projects.sql')));
        DB::unprepared(file_get_contents(database_path('data/project_members.sql')));
        DB::unprepared(file_get_contents(database_path('data/epics.sql')));
        DB::unprepared(file_get_contents(database_path('data/ticket_statuses.sql')));
        DB::unprepared(file_get_contents(database_path('data/tickets.sql')));
        DB::unprepared(file_get_contents(database_path('data/ticket_users.sql')));
        DB::unprepared(file_get_contents(database_path('data/ticket_comments.sql')));

    }
}

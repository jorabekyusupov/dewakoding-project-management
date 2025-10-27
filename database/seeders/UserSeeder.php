<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->truncate();
        User::query()->create([
            'name' => 'test',
            'email' => 'test@info.com',
            'password' => bcrypt('testtesttesttest'),
            'email_verified_at' => now(),
        ]);
        DB::unprepared(file_get_contents(database_path('data/users.sql')));
    }
}

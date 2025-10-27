<?php

namespace Database\Seeders;

use App\Models\User;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // Jalankan RoleSeeder
        $this->call([
            RoleSeeder::class,
            ShieldSeeder::class,
            UserSeeder::class,
            ProjectSeeder::class,
        ]);
    }
}

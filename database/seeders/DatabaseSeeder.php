<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Demo Free User',
            'email' => 'demo-free@example.com',
            'plan' => 'free',
        ]);

        User::factory()->create([
            'name' => 'Demo Pro User',
            'email' => 'demo-pro@example.com',
            'plan' => 'pro',
        ]);

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'plan' => 'free',
        ]);
    }
}

<?php

namespace Database\Seeders;

use App\Models\CheckResult;
use App\Models\Monitor;
use App\Models\StatusPage;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class StatusPageDemoSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first();

        // Create monitors
        $api = Monitor::factory()->for($user)->create([
            'name'           => 'API Backend',
            'url'            => 'https://api.example.com/health',
        ]);

        $web = Monitor::factory()->create([
            'user_id'        => $user->id,
            'name'           => 'Website',
            'url'            => 'https://www.example.com',
            'current_status' => 'up',
            'is_paused'      => false,
        ]);

        $db = Monitor::factory()->create([
            'user_id'        => $user->id,
            'name'           => 'Database',
            'url'            => 'https://db.example.com/ping',
            'current_status' => 'down',
            'is_paused'      => false,
        ]);

        // Create status page
        $sp = StatusPage::create([
            'user_id'     => $user->id,
            'title'       => 'Acme Services',
            'slug'        => 'acme-demo',
            'description' => 'Current status of Acme platform services.',
            'is_active'   => true,
        ]);

        $sp->monitors()->attach([
            $api->id => ['display_name' => 'API',      'sort_order' => 0],
            $web->id => ['display_name' => 'Website',   'sort_order' => 1],
            $db->id  => ['display_name' => 'Database',  'sort_order' => 2],
        ]);

        // Generate 90 days of check results
        $this->command->info('Generating 90 days of check results...');

        foreach ([$api, $web, $db] as $monitor) {
            $this->seedCheckResults($monitor);
        }

        $this->command->info("Status page ready at: /status/acme-demo");
    }

    private function seedCheckResults(Monitor $monitor): void
    {
        $records = [];
        $now = now();

        for ($day = 89; $day >= 0; $day--) {
            $date = $now->copy()->subDays($day)->startOfDay();
            // ~24 checks per day (one per hour)
            $checksPerDay = 24;

            // Simulate realistic patterns:
            // - Most days 100% uptime
            // - A few days with partial failures
            // - Rare full outage day
            $failRate = $this->failRateForDay($monitor->name, $day);

            for ($h = 0; $h < $checksPerDay; $h++) {
                $checkedAt = $date->copy()->addHours($h)->addMinutes(rand(0, 4));
                $isFail = (rand(1, 100) <= $failRate);

                $records[] = [
                    'monitor_id'       => $monitor->id,
                    'status_code'      => $isFail ? (rand(0, 1) ? 500 : 503) : 200,
                    'response_time_ms' => $isFail ? rand(5000, 30000) : rand(50, 400),
                    'is_successful'    => ! $isFail,
                    'checked_at'       => $checkedAt,
                    'created_at'       => $checkedAt,
                    'updated_at'       => $checkedAt,
                ];
            }
        }

        // Bulk insert in chunks
        foreach (array_chunk($records, 500) as $chunk) {
            CheckResult::insert($chunk);
        }

        $this->command->info("  {$monitor->name}: " . count($records) . ' check results');
    }

    private function failRateForDay(string $monitorName, int $daysAgo): int
    {
        // "Database" monitor: more incidents
        if ($monitorName === 'Database') {
            if ($daysAgo === 3) return 100;  // full outage 3 days ago
            if ($daysAgo === 15) return 40;
            if ($daysAgo === 30) return 20;
            if ($daysAgo === 60) return 60;
            if ($daysAgo === 0) return 30;   // currently degraded
            return rand(0, 100) <= 5 ? 8 : 0;
        }

        // "Website": occasional issues
        if ($monitorName === 'Website') {
            if ($daysAgo === 10) return 15;
            if ($daysAgo === 45) return 25;
            return 0;
        }

        // "API Backend": very stable
        if ($daysAgo === 20) return 5;
        return 0;
    }
}

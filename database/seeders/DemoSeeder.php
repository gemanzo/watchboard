<?php

namespace Database\Seeders;

use App\Models\CheckResult;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\StatusPage;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Loads a convincing 5-minute demo dataset.
 *
 * Usage:
 *   php artisan db:seed --class=DemoSeeder
 *
 * Idempotent — running it twice wipes the previous demo and recreates it.
 *
 * Credentials:
 *   Email:    demo@watchboard.app
 *   Password: demo1234
 */
class DemoSeeder extends Seeder
{
    public const EMAIL    = 'demo@watchboard.app';
    public const PASSWORD = 'demo1234';

    // ─── Entry point ──────────────────────────────────────────────────────────

    public function run(): void
    {
        // Idempotency: cascading deletes remove monitors, checks, incidents, pages
        User::where('email', self::EMAIL)->delete();

        // ── 1. Pro user ───────────────────────────────────────────────────────
        $user = User::create([
            'name'              => 'Alex Demo',
            'email'             => self::EMAIL,
            'password'          => Hash::make(self::PASSWORD),
            'email_verified_at' => now(),
            'plan'              => 'pro',
        ]);

        $this->command->info('User created: ' . self::EMAIL . ' / ' . self::PASSWORD);
        $this->command->newLine();

        // ── 2. Monitors + check history ───────────────────────────────────────
        $this->command->info('Creating monitors and 30 days of check history…');

        $monitors = $this->buildMonitorDefinitions();
        $created  = [];

        foreach ($monitors as $def) {
            $monitor = Monitor::create(['user_id' => $user->id] + $def['attrs']);
            $count   = $this->generateChecks($monitor, $def['checkFn']);
            $created[$def['attrs']['name']] = $monitor;

            $this->command->line(sprintf(
                '  %-26s  %s  %6d checks',
                $monitor->name,
                $monitor->current_status === 'down' ? '<fg=red>DOWN</>' : '<fg=green> UP </>',
                $count,
            ));
        }

        // ── 3. Incidents ──────────────────────────────────────────────────────
        $this->command->newLine();
        $this->command->info('Creating incidents…');
        $this->createIncidents($created);

        // ── 4. Status page ────────────────────────────────────────────────────
        $this->command->newLine();
        $this->command->info('Creating status page…');
        $this->createStatusPage($user, $created);

        // ── Summary ───────────────────────────────────────────────────────────
        $total = CheckResult::whereIn('monitor_id', collect($created)->pluck('id'))->count();

        $this->command->newLine();
        $this->command->line('  ┌─────────────────────────────────────────┐');
        $this->command->line('  │           Demo data ready ✓             │');
        $this->command->line('  ├─────────────────────────────────────────┤');
        $this->command->line('  │  Email:       ' . str_pad(self::EMAIL, 26) . '│');
        $this->command->line('  │  Password:    ' . str_pad(self::PASSWORD, 26) . '│');
        $this->command->line('  │  Checks:      ' . str_pad(number_format($total), 26) . '│');
        $this->command->line('  │  Status page: /status/demo-status       │');
        $this->command->line('  └─────────────────────────────────────────┘');
    }

    // ─── Monitor definitions ──────────────────────────────────────────────────

    /**
     * Each definition carries:
     *   attrs   – Monitor::create() attributes
     *   checkFn – callable(Carbon $at): [bool $isSuccessful, int $statusCode, int $responseMs]
     */
    private function buildMonitorDefinitions(): array
    {
        // Pre-compute fixed time anchors used by multiple profiles
        $adminOutageStart = now()->subDays(28)->setTime(14,  3, 0);
        $adminOutageEnd   = now()->subDays(28)->setTime(15, 50, 0); // 1h 47m

        $emailOutageStart = now()->subDays(15)->setTime(9,  42, 0);
        $emailOutageEnd   = now()->subDays(15)->setTime(14,  5, 0); // 4h 23m

        $legacyDownSince  = now()->subDays(2)->setTime(3, 17, 0);

        return [
            // ──────────────────────────────────────────────────────────────────
            // 1. GitHub API — very stable, fast, one minor hiccup 14 days ago
            // ──────────────────────────────────────────────────────────────────
            [
                'attrs' => [
                    'name'            => 'GitHub API',
                    'url'             => 'https://api.github.com',
                    'method'          => 'GET',
                    'interval_minutes' => 1,
                    'current_status'  => 'up',
                    'last_checked_at' => now(),
                ],
                'checkFn' => function (Carbon $at): array {
                    // Brief degradation 14 days ago: ~12% failure rate
                    if ($at->isSameDay(now()->subDays(14)) && rand(1, 100) <= 12) {
                        return [false, 500, rand(3_000, 8_000)];
                    }
                    return [true, 200, rand(80, 160)];
                },
            ],

            // ──────────────────────────────────────────────────────────────────
            // 2. Homepage — mostly stable, degraded day 18 days ago
            // ──────────────────────────────────────────────────────────────────
            [
                'attrs' => [
                    'name'            => 'Homepage',
                    'url'             => 'https://example.com',
                    'method'          => 'GET',
                    'interval_minutes' => 5,
                    'current_status'  => 'up',
                    'last_checked_at' => now(),
                ],
                'checkFn' => function (Carbon $at): array {
                    if ($at->isSameDay(now()->subDays(18)) && rand(1, 100) <= 20) {
                        return [false, rand(0, 1) ? 502 : 503, rand(4_000, 12_000)];
                    }
                    if ($at->isSameDay(now()->subDays(7)) && rand(1, 100) <= 5) {
                        return [false, 503, rand(2_000, 6_000)];
                    }
                    return [true, 200, rand(140, 320)];
                },
            ],

            // ──────────────────────────────────────────────────────────────────
            // 3. Stripe Payments — rock-solid, near-zero failures
            // ──────────────────────────────────────────────────────────────────
            [
                'attrs' => [
                    'name'            => 'Stripe Payments',
                    'url'             => 'https://stripe.com',
                    'method'          => 'GET',
                    'interval_minutes' => 1,
                    'current_status'  => 'up',
                    'last_checked_at' => now(),
                ],
                'checkFn' => function (Carbon $at): array {
                    // Tiny blip 22 days ago: 2% failure
                    if ($at->isSameDay(now()->subDays(22)) && rand(1, 100) <= 2) {
                        return [false, 503, rand(8_000, 15_000)];
                    }
                    return [true, 200, rand(55, 130)];
                },
            ],

            // ──────────────────────────────────────────────────────────────────
            // 4. Admin Dashboard — had a real outage 28 days ago (1h 47m),
            //    degraded day 12 days ago (30% errors)
            //    URL: httpbin returns a reliable 200 for demo stability
            // ──────────────────────────────────────────────────────────────────
            [
                'attrs' => [
                    'name'            => 'Admin Dashboard',
                    'url'             => 'https://httpbin.org/status/200',
                    'method'          => 'GET',
                    'interval_minutes' => 5,
                    'current_status'  => 'up',
                    'last_checked_at' => now(),
                ],
                'checkFn' => function (Carbon $at) use ($adminOutageStart, $adminOutageEnd): array {
                    // Full outage window → every check fails
                    if ($at->between($adminOutageStart, $adminOutageEnd)) {
                        return [false, 503, rand(15_000, 30_000)];
                    }
                    // Degraded day 12 days ago
                    if ($at->isSameDay(now()->subDays(12)) && rand(1, 100) <= 30) {
                        return [false, 502, rand(5_000, 10_000)];
                    }
                    return [true, 200, rand(200, 480)];
                },
            ],

            // ──────────────────────────────────────────────────────────────────
            // 5. Legacy API v1 — was mostly fine, went down 2 days ago,
            //    still down (ongoing incident)
            //    URL: httpbin consistently returns 503 → stays down
            // ──────────────────────────────────────────────────────────────────
            [
                'attrs' => [
                    'name'            => 'Legacy API v1',
                    'url'             => 'https://httpbin.org/status/503',
                    'method'          => 'GET',
                    'interval_minutes' => 5,
                    'current_status'  => 'down',
                    'last_checked_at' => now(),
                ],
                'checkFn' => function (Carbon $at) use ($legacyDownSince): array {
                    // Everything since the outage start → down
                    if ($at->greaterThanOrEqualTo($legacyDownSince)) {
                        return [false, 503, rand(10_000, 30_000)];
                    }
                    // Occasional flakiness before the big outage
                    if ($at->isSameDay(now()->subDays(10)) && rand(1, 100) <= 15) {
                        return [false, 500, rand(3_000, 8_000)];
                    }
                    if ($at->isSameDay(now()->subDays(20)) && rand(1, 100) <= 8) {
                        return [false, 500, rand(3_000, 8_000)];
                    }
                    return [true, 200, rand(150, 300)];
                },
            ],

            // ──────────────────────────────────────────────────────────────────
            // 6. Email Service — technically "up" (2xx), but response times
            //    are very high (degraded). Had a real outage 15 days ago (4h 23m).
            //    URL: httpbin /delay/2 → always 200, always ~2 s response time
            // ──────────────────────────────────────────────────────────────────
            [
                'attrs' => [
                    'name'            => 'Email Service',
                    'url'             => 'https://httpbin.org/delay/2',
                    'method'          => 'GET',
                    'interval_minutes' => 5,
                    'current_status'  => 'up',
                    'last_checked_at' => now(),
                ],
                'checkFn' => function (Carbon $at) use ($emailOutageStart, $emailOutageEnd): array {
                    // Real outage window
                    if ($at->between($emailOutageStart, $emailOutageEnd)) {
                        return [false, 503, rand(20_000, 60_000)];
                    }
                    // Always "up" but slow — gets worse recently
                    $daysAgo = (int) $at->diffInDays(now());
                    $base    = $daysAgo <= 5 ? rand(2_500, 6_000) : rand(1_200, 4_500);
                    return [true, 200, $base];
                },
            ],
        ];
    }

    // ─── Check history generation ─────────────────────────────────────────────

    /**
     * Insert 30 days of check results for $monitor, sampled at its interval.
     * Returns the number of rows inserted.
     */
    private function generateChecks(Monitor $monitor, callable $checkFn): int
    {
        $records      = [];
        $intervalSecs = $monitor->interval_minutes * 60;
        $start        = now()->subDays(30)->startOfDay();
        $at           = $start->copy();

        while ($at->lessThan(now())) {
            [$isSuccessful, $statusCode, $responseMs] = $checkFn($at);

            $records[] = [
                'monitor_id'       => $monitor->id,
                'status_code'      => $statusCode,
                'response_time_ms' => $responseMs,
                'is_successful'    => $isSuccessful,
                'checked_at'       => $at->toDateTimeString(),
                'created_at'       => $at->toDateTimeString(),
                'updated_at'       => $at->toDateTimeString(),
            ];

            // ±15 s jitter so timestamps look organic
            $at->addSeconds($intervalSecs + rand(-15, 15));
        }

        foreach (array_chunk($records, 500) as $chunk) {
            CheckResult::insert($chunk);
        }

        return count($records);
    }

    // ─── Incidents ────────────────────────────────────────────────────────────

    private function createIncidents(array $monitors): void
    {
        // ── 1. Legacy API – ongoing outage (no resolved_at) ───────────────────
        Incident::create([
            'monitor_id'       => $monitors['Legacy API v1']->id,
            'started_at'       => now()->subDays(2)->setTime(3, 17, 0),
            'resolved_at'      => null,
            'duration_seconds' => null,
        ]);
        $this->command->line('  Legacy API v1     → ongoing outage (started 2 days ago)');

        // ── 2. Admin Dashboard – 1 h 47 m outage 28 days ago ─────────────────
        $adminStart = now()->subDays(28)->setTime(14, 3, 0);
        Incident::create([
            'monitor_id'       => $monitors['Admin Dashboard']->id,
            'started_at'       => $adminStart,
            'resolved_at'      => $adminStart->copy()->addSeconds(6_420),
            'duration_seconds' => 6_420,
        ]);
        $this->command->line('  Admin Dashboard   → resolved (1h 47m, 28 days ago)');

        // ── 3. Email Service – 4 h 23 m outage 15 days ago ───────────────────
        $emailStart = now()->subDays(15)->setTime(9, 42, 0);
        Incident::create([
            'monitor_id'       => $monitors['Email Service']->id,
            'started_at'       => $emailStart,
            'resolved_at'      => $emailStart->copy()->addSeconds(15_780),
            'duration_seconds' => 15_780,
        ]);
        $this->command->line('  Email Service     → resolved (4h 23m, 15 days ago)');
    }

    // ─── Status page ──────────────────────────────────────────────────────────

    private function createStatusPage(User $user, array $monitors): void
    {
        $sp = StatusPage::create([
            'user_id'     => $user->id,
            'title'       => 'Acme Corp – Service Status',
            'slug'        => 'demo-status',
            'description' => 'Real-time status of Acme Corp platform services. Updated automatically.',
            'is_active'   => true,
        ]);

        $sp->monitors()->attach([
            $monitors['GitHub API']->id      => ['display_name' => 'API Gateway',        'sort_order' => 0],
            $monitors['Homepage']->id        => ['display_name' => 'Web Application',    'sort_order' => 1],
            $monitors['Stripe Payments']->id => ['display_name' => 'Payment Processing', 'sort_order' => 2],
            $monitors['Admin Dashboard']->id => ['display_name' => 'Admin Portal',       'sort_order' => 3],
        ]);

        $this->command->line("  Public URL: /status/demo-status  ({$sp->monitors()->count()} monitors)");
    }
}

<?php

use App\Models\CheckResult;
use App\Models\Monitor;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

// ─── Helper ───────────────────────────────────────────────────────────────────

function createChecks(Monitor $monitor, int $successful, int $failed, string $age = '1 hour'): void
{
    for ($i = 0; $i < $successful; $i++) {
        CheckResult::create([
            'monitor_id'       => $monitor->id,
            'status_code'      => 200,
            'response_time_ms' => 100,
            'is_successful'    => true,
            'checked_at'       => now()->sub($age)->addMinutes($i),
        ]);
    }

    for ($i = 0; $i < $failed; $i++) {
        CheckResult::create([
            'monitor_id'       => $monitor->id,
            'status_code'      => 500,
            'response_time_ms' => 0,
            'is_successful'    => false,
            'checked_at'       => now()->sub($age)->addMinutes($successful + $i),
        ]);
    }
}

// ─── Calculation ──────────────────────────────────────────────────────────────

test('calculates uptime percentage correctly', function () {
    $monitor = Monitor::factory()->create(['user_id' => User::factory()]);
    createChecks($monitor, successful: 9, failed: 1, age: '2 hours');

    expect($monitor->uptimePercentage('24h'))->toBe(90.0);
});

test('returns 100% when all checks are successful', function () {
    $monitor = Monitor::factory()->create(['user_id' => User::factory()]);
    createChecks($monitor, successful: 10, failed: 0, age: '2 hours');

    expect($monitor->uptimePercentage('24h'))->toBe(100.0);
});

test('returns 0% when all checks failed', function () {
    $monitor = Monitor::factory()->create(['user_id' => User::factory()]);
    createChecks($monitor, successful: 0, failed: 10, age: '2 hours');

    expect($monitor->uptimePercentage('24h'))->toBe(0.0);
});

test('returns null when no checks exist', function () {
    $monitor = Monitor::factory()->create(['user_id' => User::factory()]);

    expect($monitor->uptimePercentage('24h'))->toBeNull();
});

test('rounds to 2 decimal places', function () {
    $monitor = Monitor::factory()->create(['user_id' => User::factory()]);
    createChecks($monitor, successful: 2, failed: 1, age: '2 hours');

    // 2/3 = 66.666... → 66.67
    expect($monitor->uptimePercentage('24h'))->toBe(66.67);
});

// ─── Range filtering ─────────────────────────────────────────────────────────

test('24h range only includes checks from last 24 hours', function () {
    $monitor = Monitor::factory()->create(['user_id' => User::factory()]);

    // Recent: 9 successful
    createChecks($monitor, successful: 9, failed: 0, age: '2 hours');

    // Old: 1 failed (outside 24h window)
    CheckResult::create([
        'monitor_id'       => $monitor->id,
        'status_code'      => 500,
        'response_time_ms' => 0,
        'is_successful'    => false,
        'checked_at'       => now()->subHours(25),
    ]);

    expect($monitor->uptimePercentage('24h'))->toBe(100.0);
});

test('7d range includes checks from last 7 days', function () {
    $monitor = Monitor::factory()->create(['user_id' => User::factory()]);

    createChecks($monitor, successful: 9, failed: 1, age: '3 days');

    expect($monitor->uptimePercentage('7d'))->toBe(90.0);
});

test('30d range includes checks from last 30 days', function () {
    $monitor = Monitor::factory()->create(['user_id' => User::factory()]);

    createChecks($monitor, successful: 19, failed: 1, age: '20 days');

    expect($monitor->uptimePercentage('30d'))->toBe(95.0);
});

// ─── uptimeAll ────────────────────────────────────────────────────────────────

test('uptimeAll returns all three periods', function () {
    $monitor = Monitor::factory()->create(['user_id' => User::factory()]);
    createChecks($monitor, successful: 10, failed: 0, age: '2 hours');

    $result = $monitor->uptimeAll();

    expect($result)->toHaveKeys(['24h', '7d', '30d']);
    expect($result['24h'])->toBe(100.0);
});

// ─── Caching ──────────────────────────────────────────────────────────────────

test('result is cached for 5 minutes', function () {
    $monitor = Monitor::factory()->create(['user_id' => User::factory()]);
    createChecks($monitor, successful: 10, failed: 0, age: '2 hours');

    // First call populates cache
    $first = $monitor->uptimePercentage('24h');
    expect($first)->toBe(100.0);

    // Add a failed check — cached value should not change
    CheckResult::create([
        'monitor_id'       => $monitor->id,
        'status_code'      => 500,
        'response_time_ms' => 0,
        'is_successful'    => false,
        'checked_at'       => now(),
    ]);

    expect($monitor->uptimePercentage('24h'))->toBe(100.0);

    // After clearing cache, value should update
    Cache::forget("monitor:{$monitor->id}:uptime:24h");
    expect($monitor->uptimePercentage('24h'))->not->toBe(100.0);
});

// ─── Dashboard endpoint ──────────────────────────────────────────────────────

test('dashboard includes uptime_24h for each monitor', function () {
    $user    = User::factory()->create();
    $monitor = Monitor::factory()->create(['user_id' => $user->id]);
    createChecks($monitor, successful: 9, failed: 1, age: '2 hours');

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $monitors = $response->original->getData()['page']['props']['monitors'];
    expect($monitors[0]['uptime_24h'])->toBe(90.0);
});

// ─── Show endpoint ───────────────────────────────────────────────────────────

test('show page includes all uptime periods', function () {
    $user    = User::factory()->create();
    $monitor = Monitor::factory()->create(['user_id' => $user->id]);
    createChecks($monitor, successful: 10, failed: 0, age: '2 hours');

    $response = $this->actingAs($user)->get(route('monitors.show', $monitor));

    $response->assertOk();
    $uptime = $response->original->getData()['page']['props']['uptime'];
    expect($uptime)->toHaveKeys(['24h', '7d', '30d']);
    expect($uptime['24h'])->toBe(100.0);
});

<?php

use App\Jobs\PerformCheck;
use App\Models\CheckResult;
use App\Models\Monitor;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::fake([
        '*' => Http::response('', 200),
    ]);
});

// ─── Helper ───────────────────────────────────────────────────────────────────

function makeMonitor(array $attrs = []): Monitor
{
    return Monitor::factory()->create(array_merge([
        'user_id'          => User::factory(),
        'interval_minutes' => 5,
        'is_paused'        => false,
        'last_checked_at'  => null,
    ], $attrs));
}

// ─── Selection logic ──────────────────────────────────────────────────────────

test('dispatches job for monitor never checked', function () {
    $monitor = makeMonitor(['last_checked_at' => null]);

    $this->artisan('monitors:dispatch-checks')->assertSuccessful();

    expect($monitor->fresh()->last_checked_at)->not->toBeNull();
    $this->assertDatabaseCount('check_results', 1);
    $this->assertDatabaseHas('check_results', [
        'monitor_id'    => $monitor->id,
        'is_successful' => true,
    ]);
});

test('dispatches job for monitor whose interval has elapsed', function () {
    $monitor = makeMonitor([
        'interval_minutes' => 5,
        'last_checked_at'  => now()->subMinutes(6),
    ]);

    $this->artisan('monitors:dispatch-checks')->assertSuccessful();

    expect($monitor->fresh()->last_checked_at)->not->toBeNull();
    $this->assertDatabaseHas('check_results', [
        'monitor_id'    => $monitor->id,
        'is_successful' => true,
    ]);
});

test('does not dispatch job for monitor checked within its interval', function () {
    $monitor = makeMonitor([
        'interval_minutes' => 5,
        'last_checked_at'  => now()->subMinutes(3),
    ]);

    $this->artisan('monitors:dispatch-checks')->assertSuccessful();

    expect($monitor->fresh()->last_checked_at?->timestamp)->toBe($monitor->last_checked_at?->timestamp);
    $this->assertDatabaseMissing('check_results', [
        'monitor_id' => $monitor->id,
    ]);
});

test('does not dispatch job for paused monitor', function () {
    $monitor = makeMonitor([
        'is_paused'       => true,
        'last_checked_at' => null,
    ]);

    $this->artisan('monitors:dispatch-checks')->assertSuccessful();

    expect($monitor->fresh()->last_checked_at)->toBeNull();
    $this->assertDatabaseMissing('check_results', [
        'monitor_id' => $monitor->id,
    ]);
});

test('dispatches job exactly at interval boundary', function () {
    $monitor = makeMonitor([
        'interval_minutes' => 5,
        'last_checked_at'  => now()->subMinutes(5),
    ]);

    $this->artisan('monitors:dispatch-checks')->assertSuccessful();

    expect($monitor->fresh()->last_checked_at)->not->toBeNull();
    $this->assertDatabaseHas('check_results', [
        'monitor_id'    => $monitor->id,
        'is_successful' => true,
    ]);
});

test('dispatches only due monitors when multiple exist', function () {
    $due1 = makeMonitor(['last_checked_at' => null]);
    $due2 = makeMonitor(['interval_minutes' => 2, 'last_checked_at' => now()->subMinutes(3)]);
    $notDue = makeMonitor(['interval_minutes' => 5, 'last_checked_at' => now()->subMinutes(2)]);
    $paused = makeMonitor(['is_paused' => true, 'last_checked_at' => null]);

    $this->artisan('monitors:dispatch-checks')->assertSuccessful();

    expect($due1->fresh()->last_checked_at)->not->toBeNull();
    expect($due2->fresh()->last_checked_at)->not->toBeNull();
    expect($notDue->fresh()->last_checked_at?->timestamp)->toBe($notDue->last_checked_at?->timestamp);
    expect($paused->fresh()->last_checked_at)->toBeNull();
    expect(CheckResult::count())->toBe(2);
});

test('job is dispatched to the checks queue', function () {
    $job = new PerformCheck(makeMonitor(['last_checked_at' => null]));

    expect($job->queue)->toBe('checks');
});

test('command outputs dispatched count', function () {
    makeMonitor(['last_checked_at' => null]);
    makeMonitor(['last_checked_at' => null]);

    $this->artisan('monitors:dispatch-checks')
        ->expectsOutputToContain('Dispatched 2 check job(s).')
        ->assertSuccessful();
});

test('command outputs zero when nothing is due', function () {
    makeMonitor(['interval_minutes' => 5, 'last_checked_at' => now()->subMinutes(1)]);

    $this->artisan('monitors:dispatch-checks')
        ->expectsOutputToContain('Dispatched 0 check job(s).')
        ->assertSuccessful();
});

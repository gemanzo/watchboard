<?php

use App\Jobs\PerformCheck;
use App\Models\Monitor;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
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

    Queue::assertPushed(PerformCheck::class, fn ($job) => $job->monitor->id === $monitor->id);
});

test('dispatches job for monitor whose interval has elapsed', function () {
    $monitor = makeMonitor([
        'interval_minutes' => 5,
        'last_checked_at'  => now()->subMinutes(6),
    ]);

    $this->artisan('monitors:dispatch-checks')->assertSuccessful();

    Queue::assertPushed(PerformCheck::class, fn ($job) => $job->monitor->id === $monitor->id);
});

test('does not dispatch job for monitor checked within its interval', function () {
    $monitor = makeMonitor([
        'interval_minutes' => 5,
        'last_checked_at'  => now()->subMinutes(3),
    ]);

    $this->artisan('monitors:dispatch-checks')->assertSuccessful();

    Queue::assertNotPushed(PerformCheck::class, fn ($job) => $job->monitor->id === $monitor->id);
});

test('does not dispatch job for paused monitor', function () {
    $monitor = makeMonitor([
        'is_paused'       => true,
        'last_checked_at' => null,
    ]);

    $this->artisan('monitors:dispatch-checks')->assertSuccessful();

    Queue::assertNotPushed(PerformCheck::class, fn ($job) => $job->monitor->id === $monitor->id);
});

test('dispatches job exactly at interval boundary', function () {
    $monitor = makeMonitor([
        'interval_minutes' => 5,
        'last_checked_at'  => now()->subMinutes(5),
    ]);

    $this->artisan('monitors:dispatch-checks')->assertSuccessful();

    Queue::assertPushed(PerformCheck::class, fn ($job) => $job->monitor->id === $monitor->id);
});

test('dispatches only due monitors when multiple exist', function () {
    $due1 = makeMonitor(['last_checked_at' => null]);
    $due2 = makeMonitor(['interval_minutes' => 2, 'last_checked_at' => now()->subMinutes(3)]);
    $notDue = makeMonitor(['interval_minutes' => 5, 'last_checked_at' => now()->subMinutes(2)]);
    $paused = makeMonitor(['is_paused' => true, 'last_checked_at' => null]);

    $this->artisan('monitors:dispatch-checks')->assertSuccessful();

    Queue::assertPushed(PerformCheck::class, 2);
    Queue::assertPushed(PerformCheck::class, fn ($job) => $job->monitor->id === $due1->id);
    Queue::assertPushed(PerformCheck::class, fn ($job) => $job->monitor->id === $due2->id);
    Queue::assertNotPushed(PerformCheck::class, fn ($job) => $job->monitor->id === $notDue->id);
    Queue::assertNotPushed(PerformCheck::class, fn ($job) => $job->monitor->id === $paused->id);
});

test('job is dispatched to the checks queue', function () {
    makeMonitor(['last_checked_at' => null]);

    $this->artisan('monitors:dispatch-checks')->assertSuccessful();

    Queue::assertPushedOn('checks', PerformCheck::class);
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

<?php

use App\Events\MonitorStatusChanged;
use App\Jobs\PerformCheck;
use App\Models\CheckResult;
use App\Models\Monitor;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Event::fake([MonitorStatusChanged::class]);
});

// ─── Helper ───────────────────────────────────────────────────────────────────

/**
 * Build and run a PerformCheck for a monitor with the given prior status.
 * Http::fake is set up inside the helper so it is always the last stub registered.
 */
function checkMonitor(string $currentStatus, bool $isSuccessful = true): Monitor
{
    Http::fake(['*' => $isSuccessful
        ? Http::response('OK', 200)
        : Http::response('Error', 500),
    ]);

    $monitor = Monitor::factory()->create([
        'user_id'          => User::factory(),
        'interval_minutes' => 5,
        'current_status'   => $currentStatus,
        'is_paused'        => false,
        'last_checked_at'  => null,
    ]);

    (new PerformCheck($monitor))->handle();

    return $monitor->fresh();
}

// ─── No event expected ────────────────────────────────────────────────────────

test('no event when status stays up', function () {
    checkMonitor('up', isSuccessful: true);

    Event::assertNotDispatched(MonitorStatusChanged::class);
});

test('no event when status stays down', function () {
    checkMonitor('down', isSuccessful: false);

    Event::assertNotDispatched(MonitorStatusChanged::class);
});

test('no event on first check when service is up (unknown → up)', function () {
    checkMonitor('unknown', isSuccessful: true);

    Event::assertNotDispatched(MonitorStatusChanged::class);
});

// ─── Event expected ───────────────────────────────────────────────────────────

test('event dispatched on first check when service is down (unknown → down)', function () {
    checkMonitor('unknown', isSuccessful: false);

    Event::assertDispatched(MonitorStatusChanged::class, function ($event) {
        return $event->oldStatus === 'unknown'
            && $event->newStatus === 'down';
    });
});

test('event dispatched when monitor goes down (up → down)', function () {
    checkMonitor('up', isSuccessful: false);

    Event::assertDispatched(MonitorStatusChanged::class, function ($event) {
        return $event->oldStatus === 'up'
            && $event->newStatus === 'down';
    });
});

test('event dispatched when monitor recovers (down → up)', function () {
    checkMonitor('down', isSuccessful: true);

    Event::assertDispatched(MonitorStatusChanged::class, function ($event) {
        return $event->oldStatus === 'down'
            && $event->newStatus === 'up';
    });
});

// ─── Event payload ────────────────────────────────────────────────────────────

test('event carries the correct monitor and check result', function () {
    Http::fake(['*' => Http::response('Error', 500)]);

    $monitor = Monitor::factory()->create([
        'user_id'          => User::factory(),
        'interval_minutes' => 5,
        'current_status'   => 'up',
        'is_paused'        => false,
    ]);

    (new PerformCheck($monitor))->handle();

    Event::assertDispatched(MonitorStatusChanged::class, function ($event) use ($monitor) {
        return $event->monitor->id === $monitor->id
            && $event->checkResult instanceof CheckResult
            && $event->checkResult->monitor_id === $monitor->id;
    });
});

test('event includes downtime seconds when recovering (down → up)', function () {
    $monitor = Monitor::factory()->create([
        'user_id'          => User::factory(),
        'interval_minutes' => 5,
        'current_status'   => 'down',
        'is_paused'        => false,
    ]);

    // Prior successful check 10 minutes ago so downtime can be calculated
    CheckResult::create([
        'monitor_id'       => $monitor->id,
        'status_code'      => 200,
        'response_time_ms' => 80,
        'is_successful'    => true,
        'checked_at'       => now()->subMinutes(10),
    ]);

    Http::fake(['*' => Http::response('OK', 200)]);
    (new PerformCheck($monitor))->handle();

    Event::assertDispatched(MonitorStatusChanged::class, function ($event) {
        return $event->oldStatus === 'down'
            && $event->newStatus === 'up'
            && $event->downtimeSeconds !== null
            && $event->downtimeSeconds >= 600; // at least 10 minutes
    });
});

test('downtimeSeconds is null when no prior successful check exists', function () {
    $monitor = Monitor::factory()->create([
        'user_id'          => User::factory(),
        'interval_minutes' => 5,
        'current_status'   => 'down',
        'is_paused'        => false,
    ]);

    Http::fake(['*' => Http::response('OK', 200)]);
    (new PerformCheck($monitor))->handle();

    Event::assertDispatched(MonitorStatusChanged::class, function ($event) {
        return $event->downtimeSeconds === null;
    });
});

test('downtimeSeconds is null when going up → down', function () {
    Http::fake(['*' => Http::response('Error', 500)]);

    $monitor = Monitor::factory()->create([
        'user_id'          => User::factory(),
        'interval_minutes' => 5,
        'current_status'   => 'up',
        'is_paused'        => false,
    ]);

    (new PerformCheck($monitor))->handle();

    Event::assertDispatched(MonitorStatusChanged::class, function ($event) {
        return $event->downtimeSeconds === null;
    });
});

// ─── Exactly one event per check ─────────────────────────────────────────────

test('exactly one event is dispatched per state-changing check', function () {
    checkMonitor('up', isSuccessful: false);

    Event::assertDispatchedTimes(MonitorStatusChanged::class, 1);
});

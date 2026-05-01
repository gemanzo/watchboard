<?php

use App\Events\MonitorStatusChanged;
use App\Jobs\PerformCheck;
use App\Models\Monitor;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

// ─── Helper ───────────────────────────────────────────────────────────────────

function monitorWithThreshold(int $threshold, string $status = 'up', int $consecutiveFailures = 0): Monitor
{
    return Monitor::factory()->create([
        'user_id'                => User::factory(),
        'current_status'         => $status,
        'confirmation_threshold' => $threshold,
        'consecutive_failures'   => $consecutiveFailures,
        'is_paused'              => false,
    ]);
}

function failCheck(Monitor $monitor): Monitor
{
    Http::fake(['*' => Http::response('Error', 500)]);
    (new PerformCheck($monitor))->handle();
    return $monitor->fresh();
}

function passCheck(Monitor $monitor): Monitor
{
    Http::fake(['*' => Http::response('OK', 200)]);
    (new PerformCheck($monitor))->handle();
    return $monitor->fresh();
}

// ─── Threshold = 1 (default — existing behaviour preserved) ───────────────────

test('threshold 1: single failure flips status to down', function () {
    $monitor = monitorWithThreshold(1, 'up');
    $monitor = failCheck($monitor);

    expect($monitor->current_status)->toBe('down')
        ->and($monitor->consecutive_failures)->toBe(1);
});

test('threshold 1: single success after down flips status to up and resets counter', function () {
    $monitor = monitorWithThreshold(1, 'down', 1);
    $monitor = passCheck($monitor);

    expect($monitor->current_status)->toBe('up')
        ->and($monitor->consecutive_failures)->toBe(0);
});

// ─── Threshold = 2 ────────────────────────────────────────────────────────────

test('threshold 2: first failure keeps status up and increments counter', function () {
    Event::fake([MonitorStatusChanged::class]);

    $monitor = monitorWithThreshold(2, 'up');
    $monitor = failCheck($monitor);

    expect($monitor->current_status)->toBe('up')
        ->and($monitor->consecutive_failures)->toBe(1);

    Event::assertNotDispatched(MonitorStatusChanged::class);
});

test('threshold 2: second consecutive failure flips status to down', function () {
    Event::fake([MonitorStatusChanged::class]);

    $monitor = monitorWithThreshold(2, 'up', 1);
    $monitor = failCheck($monitor);

    expect($monitor->current_status)->toBe('down')
        ->and($monitor->consecutive_failures)->toBe(2);

    Event::assertDispatched(MonitorStatusChanged::class, fn ($e) =>
        $e->oldStatus === 'up' && $e->newStatus === 'down'
    );
});

test('threshold 2: success between failures resets counter without alerting', function () {
    Event::fake([MonitorStatusChanged::class]);

    $monitor = monitorWithThreshold(2, 'up', 1);
    $monitor = passCheck($monitor);

    expect($monitor->current_status)->toBe('up')
        ->and($monitor->consecutive_failures)->toBe(0);

    Event::assertNotDispatched(MonitorStatusChanged::class);
});

// ─── Threshold = 3 ────────────────────────────────────────────────────────────

test('threshold 3: two failures do not change status', function () {
    Event::fake([MonitorStatusChanged::class]);

    $monitor = monitorWithThreshold(3, 'up');

    failCheck($monitor);
    $monitor->refresh();
    expect($monitor->current_status)->toBe('up')->and($monitor->consecutive_failures)->toBe(1);

    failCheck($monitor);
    $monitor->refresh();
    expect($monitor->current_status)->toBe('up')->and($monitor->consecutive_failures)->toBe(2);

    Event::assertNotDispatched(MonitorStatusChanged::class);
});

test('threshold 3: third consecutive failure triggers down and event', function () {
    Event::fake([MonitorStatusChanged::class]);

    $monitor = monitorWithThreshold(3, 'up', 2);
    $monitor = failCheck($monitor);

    expect($monitor->current_status)->toBe('down')
        ->and($monitor->consecutive_failures)->toBe(3);

    Event::assertDispatched(MonitorStatusChanged::class, fn ($e) =>
        $e->oldStatus === 'up' && $e->newStatus === 'down'
    );
});

// ─── Recovery resets counter ──────────────────────────────────────────────────

test('successful check resets consecutive_failures to 0', function () {
    $monitor = monitorWithThreshold(3, 'down', 3);
    $monitor = passCheck($monitor);

    expect($monitor->consecutive_failures)->toBe(0);
});

test('no event emitted when success keeps status up (counter was below threshold)', function () {
    Event::fake([MonitorStatusChanged::class]);

    $monitor = monitorWithThreshold(3, 'up', 2); // two failures but still up
    $monitor = passCheck($monitor);

    expect($monitor->current_status)->toBe('up')
        ->and($monitor->consecutive_failures)->toBe(0);

    Event::assertNotDispatched(MonitorStatusChanged::class);
});

// ─── unknown initial status ────────────────────────────────────────────────────

test('threshold 2: first failure from unknown keeps status unknown', function () {
    Event::fake([MonitorStatusChanged::class]);

    $monitor = monitorWithThreshold(2, 'unknown');
    $monitor = failCheck($monitor);

    expect($monitor->current_status)->toBe('unknown')
        ->and($monitor->consecutive_failures)->toBe(1);

    Event::assertNotDispatched(MonitorStatusChanged::class);
});

test('threshold 2: second failure from unknown triggers down event', function () {
    Event::fake([MonitorStatusChanged::class]);

    $monitor = monitorWithThreshold(2, 'unknown', 1);
    $monitor = failCheck($monitor);

    expect($monitor->current_status)->toBe('down');

    Event::assertDispatched(MonitorStatusChanged::class, fn ($e) =>
        $e->oldStatus === 'unknown' && $e->newStatus === 'down'
    );
});

// ─── Already down — failures keep counter moving but no duplicate event ────────

test('failures while already down do not re-dispatch MonitorStatusChanged', function () {
    Event::fake([MonitorStatusChanged::class]);

    $monitor = monitorWithThreshold(1, 'down', 1);
    failCheck($monitor);

    Event::assertNotDispatched(MonitorStatusChanged::class);
});

// ─── Connection error also counts toward threshold ─────────────────────────────

test('connection error counts toward threshold', function () {
    Http::fake(['*' => fn () => throw new ConnectionException('refused')]);

    $monitor = monitorWithThreshold(2, 'up', 1); // one failure already recorded
    (new PerformCheck($monitor))->handle();
    $monitor->refresh();

    expect($monitor->current_status)->toBe('down')
        ->and($monitor->consecutive_failures)->toBe(2);
});

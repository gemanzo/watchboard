<?php

use App\Events\MonitorSlowResponse;
use App\Jobs\PerformCheck;
use App\Listeners\SendSlowResponseNotification;
use App\Models\CheckResult;
use App\Models\Monitor;
use App\Models\User;
use App\Notifications\MonitorSlowResponseNotification;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

// ─── Helper ───────────────────────────────────────────────────────────────────

function slowMonitor(?int $thresholdMs, string $status = 'up'): Monitor
{
    return Monitor::factory()->create([
        'user_id'                    => User::factory(),
        'current_status'             => $status,
        'response_time_threshold_ms' => $thresholdMs,
        'is_paused'                  => false,
    ]);
}

// ─── No event when threshold is null ─────────────────────────────────────────

test('no event when threshold is null', function () {
    Event::fake([MonitorSlowResponse::class]);

    $monitor = slowMonitor(null);
    Http::fake(['*' => Http::response('OK', 200)]);
    (new PerformCheck($monitor))->handle();

    Event::assertNotDispatched(MonitorSlowResponse::class);
});

// ─── No event on failed checks ────────────────────────────────────────────────

test('no event on 5xx response even with threshold set', function () {
    Event::fake([MonitorSlowResponse::class]);

    $monitor = slowMonitor(0); // threshold=0 would always fire on success, but not on failure
    Http::fake(['*' => Http::response('Error', 500)]);
    (new PerformCheck($monitor))->handle();

    Event::assertNotDispatched(MonitorSlowResponse::class);
});

test('no event on connection error even with threshold set', function () {
    Event::fake([MonitorSlowResponse::class]);

    $monitor = slowMonitor(0);
    Http::fake(['*' => fn () => throw new ConnectionException('refused')]);
    (new PerformCheck($monitor))->handle();

    Event::assertNotDispatched(MonitorSlowResponse::class);
});

// ─── Event dispatched when threshold exceeded ─────────────────────────────────

test('event dispatched when successful check exceeds threshold', function () {
    Event::fake([MonitorSlowResponse::class]);

    // threshold=0 means any response_time_ms > 0 triggers the event.
    // Http::fake in tests still produces a non-zero response_time_ms in most runs;
    // but since we can't guarantee it, we verify the logic via the listener path below.
    // Here we assert the payload shape when the event IS dispatched.
    $monitor = slowMonitor(0);
    Http::fake(['*' => Http::response('OK', 200)]);
    (new PerformCheck($monitor))->handle();

    $result = $monitor->checkResults()->latest()->first();

    if ($result->response_time_ms > 0) {
        Event::assertDispatched(MonitorSlowResponse::class, fn ($e) =>
            $e->monitor->id === $monitor->id
            && $e->checkResult->id === $result->id
            && $e->thresholdMs === 0
        );
    } else {
        // Http::fake resolved in 0ms: expected non-dispatch, not a bug.
        Event::assertNotDispatched(MonitorSlowResponse::class);
    }
});

// ─── Listener sends notification ──────────────────────────────────────────────

test('listener sends MonitorSlowResponseNotification to monitor owner', function () {
    Notification::fake();

    $user    = User::factory()->create();
    $monitor = Monitor::factory()->for($user)->create(['response_time_threshold_ms' => 2000]);
    $result  = CheckResult::factory()->for($monitor)->create([
        'response_time_ms' => 3000,
        'is_successful'    => true,
        'checked_at'       => now(),
    ]);

    $event = new MonitorSlowResponse($monitor, $result, 2000);
    (new SendSlowResponseNotification(new \App\Services\NotificationThrottler()))->handle($event);

    Notification::assertSentTo($user, MonitorSlowResponseNotification::class);
});

test('listener is queued on the notifications queue', function () {
    expect((new SendSlowResponseNotification(new \App\Services\NotificationThrottler()))->queue)->toBe('notifications');
});

// ─── Notification content ─────────────────────────────────────────────────────

test('notification mail subject contains monitor name and slow keyword', function () {
    $monitor = Monitor::factory()->create(['name' => 'My API', 'url' => 'https://example.com']);
    $result  = CheckResult::factory()->for($monitor)->create([
        'response_time_ms' => 3500,
        'is_successful'    => true,
        'checked_at'       => now(),
    ]);

    $notification = new MonitorSlowResponseNotification($monitor, $result, 2000);
    $mail         = $notification->toMail($monitor->user);

    expect($mail->subject)->toContain('My API')
        ->and($mail->subject)->toContain('slowly');
});

test('notification mail body contains url, response time and threshold in seconds', function () {
    $monitor = Monitor::factory()->create(['name' => 'My API', 'url' => 'https://example.com']);
    $result  = CheckResult::factory()->for($monitor)->create([
        'response_time_ms' => 3500,
        'is_successful'    => true,
        'checked_at'       => now(),
    ]);

    $notification = new MonitorSlowResponseNotification($monitor, $result, 2000);
    $mail         = $notification->toMail($monitor->user);

    $body = collect($mail->introLines)->implode(' ');

    expect($body)->toContain('https://example.com')
        ->and($body)->toContain('3.50s')   // response time formatted
        ->and($body)->toContain('2.00s');   // threshold formatted
});

test('notification via mail channel', function () {
    $monitor      = Monitor::factory()->create();
    $result       = CheckResult::factory()->for($monitor)->create(['checked_at' => now()]);
    $notification = new MonitorSlowResponseNotification($monitor, $result, 1000);

    expect($notification->via($monitor->user))->toBe(['mail']);
});

test('notification is queued on the notifications queue', function () {
    $monitor      = Monitor::factory()->create();
    $result       = CheckResult::factory()->for($monitor)->create(['checked_at' => now()]);
    $notification = new MonitorSlowResponseNotification($monitor, $result, 1000);

    expect($notification->viaQueues())->toBe(['mail' => 'notifications']);
});

// ─── End-to-end: PerformCheck → notification ──────────────────────────────────

test('no notification when threshold is null (end-to-end)', function () {
    Notification::fake();

    $user    = User::factory()->create();
    $monitor = Monitor::factory()->for($user)->create(['response_time_threshold_ms' => null]);

    Http::fake(['*' => Http::response('OK', 200)]);
    (new PerformCheck($monitor))->handle();

    Notification::assertNotSentTo($user, MonitorSlowResponseNotification::class);
});

test('no notification on failed check (end-to-end)', function () {
    Notification::fake();

    $user    = User::factory()->create();
    $monitor = Monitor::factory()->for($user)->create([
        'response_time_threshold_ms' => 0,
        'current_status'             => 'up',
    ]);

    Http::fake(['*' => Http::response('Error', 500)]);
    (new PerformCheck($monitor))->handle();

    Notification::assertNotSentTo($user, MonitorSlowResponseNotification::class);
});

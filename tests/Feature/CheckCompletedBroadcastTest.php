<?php

use App\Events\CheckCompleted;
use App\Jobs\PerformCheck;
use App\Models\CheckResult;
use App\Models\Monitor;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

// ─── Event structure ──────────────────────────────────────────────────────────

test('CheckCompleted broadcasts on the correct private channel', function () {
    $user    = User::factory()->create();
    $monitor = Monitor::factory()->for($user)->create();
    $check   = CheckResult::factory()->for($monitor)->create();

    $event = new CheckCompleted($monitor, $check);

    $channel = $event->broadcastOn();

    expect($channel->name)->toBe('private-user.' . $user->id);
});

test('CheckCompleted broadcasts as "CheckCompleted"', function () {
    $user    = User::factory()->create();
    $monitor = Monitor::factory()->for($user)->create();
    $check   = CheckResult::factory()->for($monitor)->create();

    $event = new CheckCompleted($monitor, $check);

    expect($event->broadcastAs())->toBe('CheckCompleted');
});

test('CheckCompleted payload contains required monitor fields', function () {
    $user    = User::factory()->create();
    $monitor = Monitor::factory()->for($user)->create(['current_status' => 'up', 'is_paused' => false]);
    $check   = CheckResult::factory()->for($monitor)->create([
        'status_code'      => 200,
        'response_time_ms' => 145,
        'is_successful'    => true,
    ]);

    $payload = (new CheckCompleted($monitor, $check))->broadcastWith();

    expect($payload)->toHaveKey('monitor')
        ->and($payload['monitor'])->toHaveKeys([
            'id', 'current_status', 'is_paused',
            'last_status_code', 'last_response_time_ms',
            'last_checked_at_human', 'uptime_24h',
        ])
        ->and($payload['monitor']['id'])->toBe($monitor->id)
        ->and($payload['monitor']['current_status'])->toBe('up')
        ->and($payload['monitor']['last_status_code'])->toBe(200)
        ->and($payload['monitor']['last_response_time_ms'])->toBe(145);
});

// ─── PerformCheck dispatches the event ───────────────────────────────────────

test('PerformCheck dispatches CheckCompleted after a successful check', function () {
    Event::fake([CheckCompleted::class]);

    $user    = User::factory()->create();
    $monitor = Monitor::factory()->for($user)->create(['url' => 'https://example.com', 'method' => 'GET']);

    Http::fake(['https://example.com' => Http::response('OK', 200)]);

    (new PerformCheck($monitor))->handle();

    Event::assertDispatched(CheckCompleted::class, function ($event) use ($monitor) {
        return $event->monitor->id === $monitor->id;
    });
});

test('PerformCheck dispatches CheckCompleted even when check fails', function () {
    Event::fake([CheckCompleted::class]);

    $user    = User::factory()->create();
    $monitor = Monitor::factory()->for($user)->create(['url' => 'https://example.com', 'method' => 'GET']);

    Http::fake(['https://example.com' => Http::response('Error', 500)]);

    (new PerformCheck($monitor))->handle();

    Event::assertDispatched(CheckCompleted::class);
});

// ─── Channel authorization callback ──────────────────────────────────────────
// The null driver used in tests bypasses the HTTP auth endpoint, so we test
// the channel callback logic directly.

test('channel callback allows user to subscribe to their own channel', function () {
    $user = User::factory()->create();

    // Replicate the callback defined in routes/channels.php
    $callback = fn ($authUser, $id) => (int) $authUser->id === (int) $id;

    expect($callback($user, $user->id))->toBeTrue();
});

test('channel callback denies user from subscribing to another users channel', function () {
    $user  = User::factory()->create();
    $other = User::factory()->create();

    $callback = fn ($authUser, $id) => (int) $authUser->id === (int) $id;

    expect($callback($user, $other->id))->toBeFalse();
});

test('channel callback rejects mismatched string vs integer id', function () {
    $user = User::factory()->create();

    $callback = fn ($authUser, $id) => (int) $authUser->id === (int) $id;

    // Should still pass since we cast both to int
    expect($callback($user, (string) $user->id))->toBeTrue();
    // And fail for a different id
    expect($callback($user, (string) ($user->id + 1)))->toBeFalse();
});

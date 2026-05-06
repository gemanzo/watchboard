<?php

use App\Events\MonitorStatusChanged;
use App\Events\MonitorSlowResponse;
use App\Listeners\SendDownNotification;
use App\Listeners\SendRecoveryNotification;
use App\Listeners\SendSlowResponseNotification;
use App\Models\CheckResult;
use App\Models\Monitor;
use App\Models\NotificationChannel;
use App\Models\User;
use App\Notifications\Channels\WebhookChannelHandler;
use App\Services\NotificationDispatcher;
use App\Services\NotificationThrottler;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

// ─── Helpers ──────────────────────────────────────────────────────────────────

function webhookMonitor(array $attrs = []): Monitor
{
    return Monitor::factory()->create(array_merge(
        ['user_id' => User::factory()],
        $attrs,
    ));
}

function webhookDownEvent(Monitor $monitor): MonitorStatusChanged
{
    $result = CheckResult::create([
        'monitor_id'       => $monitor->id,
        'status_code'      => 500,
        'response_time_ms' => 100,
        'is_successful'    => false,
        'checked_at'       => now(),
    ]);

    return new MonitorStatusChanged($monitor, 'up', 'down', $result);
}

function webhookSlowEvent(Monitor $monitor): MonitorSlowResponse
{
    $result = CheckResult::create([
        'monitor_id'       => $monitor->id,
        'status_code'      => 200,
        'response_time_ms' => 3000,
        'is_successful'    => true,
        'checked_at'       => now(),
    ]);

    return new MonitorSlowResponse($monitor, $result, 2000);
}

// ─── Webhook payload structure ────────────────────────────────────────────────

test('webhook handler sends POST with correct event and monitor fields', function () {
    Http::fake(['*' => Http::response('ok', 200)]);

    $monitor = webhookMonitor();
    $channel = NotificationChannel::factory()->webhook()->create([
        'user_id' => $monitor->user_id,
        'config'  => ['url' => 'https://example.com/hook', 'secret' => null, 'timeout_seconds' => 10],
    ]);

    $checkResult = CheckResult::create([
        'monitor_id'       => $monitor->id,
        'status_code'      => 503,
        'response_time_ms' => 200,
        'is_successful'    => false,
        'checked_at'       => now(),
    ]);

    (new WebhookChannelHandler)->send($channel, 'monitor.down', [
        'monitor'      => $monitor,
        'check_result' => $checkResult,
    ]);

    Http::assertSent(function ($request) use ($monitor) {
        $body = $request->data();
        return $request->url() === 'https://example.com/hook'
            && $body['event'] === 'monitor.down'
            && $body['monitor']['id'] === $monitor->id
            && $body['check']['status_code'] === 503;
    });
});

// ─── HMAC signature ───────────────────────────────────────────────────────────

test('webhook handler adds X-WatchBoard-Signature header when secret is set', function () {
    Http::fake(['*' => Http::response('ok', 200)]);

    $monitor = webhookMonitor();
    $secret  = 'super-secret-key';
    $channel = NotificationChannel::factory()->webhook()->create([
        'user_id' => $monitor->user_id,
        'config'  => ['url' => 'https://example.com/hook', 'secret' => $secret, 'timeout_seconds' => 10],
    ]);

    (new WebhookChannelHandler)->send($channel, 'monitor.test', [
        'monitor'      => $monitor,
        'check_result' => null,
    ]);

    Http::assertSent(function ($request) use ($secret) {
        $sig    = $request->header('X-WatchBoard-Signature')[0] ?? '';
        $body   = $request->body();
        $expect = 'sha256=' . hash_hmac('sha256', $body, $secret);

        return str_starts_with($sig, 'sha256=') && $sig === $expect;
    });
});

test('webhook handler does NOT add signature header when secret is empty', function () {
    Http::fake(['*' => Http::response('ok', 200)]);

    $monitor = webhookMonitor();
    $channel = NotificationChannel::factory()->webhook()->create([
        'user_id' => $monitor->user_id,
        'config'  => ['url' => 'https://example.com/hook', 'secret' => null, 'timeout_seconds' => 10],
    ]);

    (new WebhookChannelHandler)->send($channel, 'monitor.test', [
        'monitor' => $monitor, 'check_result' => null,
    ]);

    Http::assertSent(fn ($req) => empty($req->header('X-WatchBoard-Signature')));
});

// ─── Failure isolation ────────────────────────────────────────────────────────

test('failing webhook does not block other channels from receiving notifications', function () {
    Http::fake([
        'https://bad.example.com/*'  => Http::response('error', 500),
        'https://good.example.com/*' => Http::response('ok', 200),
    ]);
    Notification::fake();

    $monitor = webhookMonitor(['last_notified_at' => null]);

    // Two webhook channels: first fails, second should still receive
    NotificationChannel::factory()->webhook()->create([
        'user_id' => $monitor->user_id,
        'config'  => ['url' => 'https://bad.example.com/hook', 'secret' => null, 'timeout_seconds' => 5],
    ]);
    NotificationChannel::factory()->webhook()->create([
        'user_id' => $monitor->user_id,
        'config'  => ['url' => 'https://good.example.com/hook', 'secret' => null, 'timeout_seconds' => 5],
    ]);

    $event = webhookDownEvent($monitor);
    (new SendDownNotification(new NotificationThrottler))->handle($event);

    Http::assertSent(fn ($req) => str_contains($req->url(), 'bad.example.com'));
    Http::assertSent(fn ($req) => str_contains($req->url(), 'good.example.com'));
});

test('inactive channel does not receive webhook', function () {
    Http::fake(['*' => Http::response('ok', 200)]);
    Notification::fake();

    $monitor = webhookMonitor(['last_notified_at' => null]);

    NotificationChannel::factory()->webhook()->create([
        'user_id'   => $monitor->user_id,
        'is_active' => false,
        'config'    => ['url' => 'https://example.com/hook', 'secret' => null, 'timeout_seconds' => 5],
    ]);

    $event = webhookDownEvent($monitor);
    (new SendDownNotification(new NotificationThrottler))->handle($event);

    Http::assertNothingSent();
});

// ─── Dispatcher dispatches to active channels ─────────────────────────────────

test('dispatcher sends to all active channels of user', function () {
    Http::fake(['*' => Http::response('ok', 200)]);

    $user = User::factory()->create();
    NotificationChannel::factory()->webhook()->create([
        'user_id'   => $user->id,
        'is_active' => true,
        'config'    => ['url' => 'https://a.example.com/hook', 'secret' => null, 'timeout_seconds' => 5],
    ]);
    NotificationChannel::factory()->webhook()->create([
        'user_id'   => $user->id,
        'is_active' => true,
        'config'    => ['url' => 'https://b.example.com/hook', 'secret' => null, 'timeout_seconds' => 5],
    ]);
    NotificationChannel::factory()->webhook()->create([
        'user_id'   => $user->id,
        'is_active' => false,
        'config'    => ['url' => 'https://c.example.com/hook', 'secret' => null, 'timeout_seconds' => 5],
    ]);

    $monitor = Monitor::factory()->create(['user_id' => $user->id]);
    (new NotificationDispatcher)->dispatch($user, 'monitor.down', [
        'monitor'      => $monitor,
        'check_result' => null,
    ]);

    Http::assertSentCount(2); // only the 2 active ones
});

// ─── Recovery payload ─────────────────────────────────────────────────────────

test('webhook payload for recovery includes downtime_seconds', function () {
    Http::fake(['*' => Http::response('ok', 200)]);

    $monitor = webhookMonitor(['last_notified_at' => null]);
    NotificationChannel::factory()->webhook()->create([
        'user_id' => $monitor->user_id,
        'config'  => ['url' => 'https://example.com/hook', 'secret' => null, 'timeout_seconds' => 5],
    ]);

    $checkResult = CheckResult::create([
        'monitor_id'       => $monitor->id,
        'status_code'      => 200,
        'response_time_ms' => 80,
        'is_successful'    => true,
        'checked_at'       => now(),
    ]);

    $event = new MonitorStatusChanged($monitor, 'down', 'up', $checkResult, 300);
    Notification::fake();
    (new SendRecoveryNotification(new NotificationThrottler))->handle($event);

    Http::assertSent(function ($request) {
        $body = $request->data();
        return $body['event'] === 'monitor.recovered'
            && $body['downtime_seconds'] === 300;
    });
});

// ─── Slow response payload ────────────────────────────────────────────────────

test('webhook payload for slow response includes threshold_ms', function () {
    Http::fake(['*' => Http::response('ok', 200)]);

    $monitor = webhookMonitor(['last_notified_at' => null]);
    NotificationChannel::factory()->webhook()->create([
        'user_id' => $monitor->user_id,
        'config'  => ['url' => 'https://example.com/hook', 'secret' => null, 'timeout_seconds' => 5],
    ]);

    Notification::fake();
    (new SendSlowResponseNotification(new NotificationThrottler))->handle(webhookSlowEvent($monitor));

    Http::assertSent(function ($request) {
        $body = $request->data();
        return $body['event'] === 'monitor.slow_response'
            && $body['threshold_ms'] === 2000;
    });
});

// ─── Test endpoint ────────────────────────────────────────────────────────────

test('test endpoint sends test payload and returns success', function () {
    Http::fake(['*' => Http::response('ok', 200)]);

    $user    = User::factory()->create();
    $channel = NotificationChannel::factory()->webhook()->create([
        'user_id' => $user->id,
        'config'  => ['url' => 'https://example.com/hook', 'secret' => null, 'timeout_seconds' => 5],
    ]);

    $this->actingAs($user)
        ->postJson(route('notification-channels.test', $channel))
        ->assertOk()
        ->assertJsonPath('success', true);

    Http::assertSent(fn ($req) => $req->data()['event'] === 'monitor.test');
});

test('test endpoint returns error when webhook fails', function () {
    Http::fake(['*' => Http::response('error', 500)]);

    $user    = User::factory()->create();
    $channel = NotificationChannel::factory()->webhook()->create([
        'user_id' => $user->id,
        'config'  => ['url' => 'https://example.com/hook', 'secret' => null, 'timeout_seconds' => 5],
    ]);

    $this->actingAs($user)
        ->postJson(route('notification-channels.test', $channel))
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

test('test endpoint is forbidden for another user\'s channel', function () {
    $user    = User::factory()->create();
    $other   = User::factory()->create();
    $channel = NotificationChannel::factory()->webhook()->create(['user_id' => $other->id]);

    $this->actingAs($user)
        ->postJson(route('notification-channels.test', $channel))
        ->assertForbidden();
});

<?php

use App\Events\MonitorStatusChanged;
use App\Events\MonitorSlowResponse;
use App\Listeners\SendDownNotification;
use App\Listeners\SendRecoveryNotification;
use App\Listeners\SendSlowResponseNotification;
use App\Models\CheckResult;
use App\Models\Monitor;
use App\Models\User;
use App\Notifications\MonitorDownNotification;
use App\Notifications\MonitorRecoveredNotification;
use App\Notifications\MonitorSlowResponseNotification;
use App\Services\NotificationThrottler;
use Illuminate\Support\Facades\Notification;

// ─── Helpers ──────────────────────────────────────────────────────────────────

function throttleMonitor_(array $attrs = []): Monitor
{
    return Monitor::factory()->create(array_merge(
        ['user_id' => User::factory()],
        $attrs,
    ));
}

function throttleDownEvent(Monitor $monitor): MonitorStatusChanged
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

function throttleRecoveryEvent(Monitor $monitor): MonitorStatusChanged
{
    $result = CheckResult::create([
        'monitor_id'       => $monitor->id,
        'status_code'      => 200,
        'response_time_ms' => 80,
        'is_successful'    => true,
        'checked_at'       => now(),
    ]);

    return new MonitorStatusChanged($monitor, 'down', 'up', $result, 300);
}

function throttleSlowEvent(Monitor $monitor): MonitorSlowResponse
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

// ─── NotificationThrottler unit tests ────────────────────────────────────────

test('shouldSend returns true when last_notified_at is null', function () {
    $monitor = throttleMonitor_(['last_notified_at' => null, 'notification_cooldown_minutes' => 15]);

    expect((new NotificationThrottler)->shouldSend($monitor))->toBeTrue();
});

test('shouldSend returns true when cooldown has elapsed', function () {
    $monitor = throttleMonitor_([
        'last_notified_at'              => now()->subMinutes(16),
        'notification_cooldown_minutes' => 15,
    ]);

    expect((new NotificationThrottler)->shouldSend($monitor))->toBeTrue();
});

test('shouldSend returns false when still within cooldown', function () {
    $monitor = throttleMonitor_([
        'last_notified_at'              => now()->subMinutes(10),
        'notification_cooldown_minutes' => 15,
    ]);

    expect((new NotificationThrottler)->shouldSend($monitor))->toBeFalse();
});

test('shouldSend returns false one second before cooldown expires', function () {
    $monitor = throttleMonitor_([
        'last_notified_at'              => now()->subMinutes(15)->addSeconds(5),
        'notification_cooldown_minutes' => 15,
    ]);

    expect((new NotificationThrottler)->shouldSend($monitor))->toBeFalse();
});

test('recovery bypasses cooldown when recovery_bypass_cooldown is true', function () {
    $monitor = throttleMonitor_([
        'last_notified_at'              => now()->subMinutes(2),
        'notification_cooldown_minutes' => 15,
        'recovery_bypass_cooldown'      => true,
    ]);

    expect((new NotificationThrottler)->shouldSend($monitor, isRecovery: true))->toBeTrue();
});

test('recovery does not bypass cooldown when recovery_bypass_cooldown is false', function () {
    $monitor = throttleMonitor_([
        'last_notified_at'              => now()->subMinutes(2),
        'notification_cooldown_minutes' => 15,
        'recovery_bypass_cooldown'      => false,
    ]);

    expect((new NotificationThrottler)->shouldSend($monitor, isRecovery: true))->toBeFalse();
});

test('recordNotificationSent updates last_notified_at', function () {
    $monitor = throttleMonitor_(['last_notified_at' => null]);

    (new NotificationThrottler)->recordNotificationSent($monitor);

    expect($monitor->fresh()->last_notified_at)->not->toBeNull()
        ->and($monitor->fresh()->last_notified_at->diffInSeconds(now()))->toBeLessThan(2);
});

// ─── Down notification cooldown ───────────────────────────────────────────────

test('down notification is sent when no previous notification', function () {
    Notification::fake();

    $monitor = throttleMonitor_(['last_notified_at' => null]);
    (new SendDownNotification(new NotificationThrottler))->handle(throttleDownEvent($monitor));

    Notification::assertSentTo($monitor->user, MonitorDownNotification::class);
});

test('down notification is blocked within cooldown', function () {
    Notification::fake();

    $monitor = throttleMonitor_([
        'last_notified_at'              => now()->subMinutes(5),
        'notification_cooldown_minutes' => 15,
    ]);

    (new SendDownNotification(new NotificationThrottler))->handle(throttleDownEvent($monitor));

    Notification::assertNothingSent();
});

test('down notification is sent after cooldown expires', function () {
    Notification::fake();

    $monitor = throttleMonitor_([
        'last_notified_at'              => now()->subMinutes(20),
        'notification_cooldown_minutes' => 15,
    ]);

    (new SendDownNotification(new NotificationThrottler))->handle(throttleDownEvent($monitor));

    Notification::assertSentTo($monitor->user, MonitorDownNotification::class);
});

test('down notification sending updates last_notified_at', function () {
    Notification::fake();

    $monitor = throttleMonitor_(['last_notified_at' => null]);
    (new SendDownNotification(new NotificationThrottler))->handle(throttleDownEvent($monitor));

    expect($monitor->fresh()->last_notified_at)->not->toBeNull();
});

test('blocked down notification does not update last_notified_at', function () {
    Notification::fake();

    $sentAt = now()->subMinutes(5);
    $monitor = throttleMonitor_([
        'last_notified_at'              => $sentAt,
        'notification_cooldown_minutes' => 15,
    ]);

    (new SendDownNotification(new NotificationThrottler))->handle(throttleDownEvent($monitor));

    // DB timestamps have second precision; compare within a 1-second tolerance
    expect($monitor->fresh()->last_notified_at->diffInSeconds($sentAt))->toBeLessThan(2);
});

// ─── Recovery notification cooldown ──────────────────────────────────────────

test('recovery notification bypasses cooldown by default', function () {
    Notification::fake();

    $monitor = throttleMonitor_([
        'last_notified_at'              => now()->subMinutes(2),
        'notification_cooldown_minutes' => 15,
        'recovery_bypass_cooldown'      => true,
    ]);

    (new SendRecoveryNotification(new NotificationThrottler))->handle(throttleRecoveryEvent($monitor));

    Notification::assertSentTo($monitor->user, MonitorRecoveredNotification::class);
});

test('recovery notification respects cooldown when bypass is disabled', function () {
    Notification::fake();

    $monitor = throttleMonitor_([
        'last_notified_at'              => now()->subMinutes(2),
        'notification_cooldown_minutes' => 15,
        'recovery_bypass_cooldown'      => false,
    ]);

    (new SendRecoveryNotification(new NotificationThrottler))->handle(throttleRecoveryEvent($monitor));

    Notification::assertNothingSent();
});

test('recovery notification updates last_notified_at when sent', function () {
    Notification::fake();

    $monitor = throttleMonitor_([
        'last_notified_at'         => null,
        'recovery_bypass_cooldown' => true,
    ]);

    (new SendRecoveryNotification(new NotificationThrottler))->handle(throttleRecoveryEvent($monitor));

    expect($monitor->fresh()->last_notified_at)->not->toBeNull();
});

// ─── Slow response notification cooldown ─────────────────────────────────────

test('slow response notification is blocked within cooldown', function () {
    Notification::fake();

    $monitor = throttleMonitor_([
        'last_notified_at'              => now()->subMinutes(3),
        'notification_cooldown_minutes' => 15,
    ]);

    (new SendSlowResponseNotification(new NotificationThrottler))->handle(throttleSlowEvent($monitor));

    Notification::assertNothingSent();
});

test('slow response notification is sent when cooldown has elapsed', function () {
    Notification::fake();

    $monitor = throttleMonitor_([
        'last_notified_at'              => now()->subMinutes(20),
        'notification_cooldown_minutes' => 15,
    ]);

    (new SendSlowResponseNotification(new NotificationThrottler))->handle(throttleSlowEvent($monitor));

    Notification::assertSentTo($monitor->user, MonitorSlowResponseNotification::class);
});

// ─── Cross-type cooldown (shared last_notified_at) ───────────────────────────

test('slow response notification is blocked after a recent down notification', function () {
    Notification::fake();

    // Simulate: down notification was just sent
    $monitor = throttleMonitor_([
        'last_notified_at'              => now()->subMinutes(3),
        'notification_cooldown_minutes' => 15,
    ]);

    // Now slow response fires — should be blocked by the shared cooldown
    (new SendSlowResponseNotification(new NotificationThrottler))->handle(throttleSlowEvent($monitor));

    Notification::assertNothingSent();
});

// ─── Validation ───────────────────────────────────────────────────────────────

test('store validates notification_cooldown_minutes must be in allowed set', function () {
    $user = User::factory()->create(['plan' => 'free']);

    $this->actingAs($user)
        ->post(route('monitors.store'), [
            'url'                           => 'https://example.com',
            'method'                        => 'GET',
            'interval_minutes'              => 5,
            'notification_cooldown_minutes' => 20, // not in [5,10,15,30,60]
        ])
        ->assertSessionHasErrors('notification_cooldown_minutes');
});

test('store accepts valid notification_cooldown_minutes for pro users', function () {
    $user = User::factory()->create(['plan' => 'pro']);

    foreach ([5, 10, 15, 30, 60] as $minutes) {
        $this->actingAs($user)
            ->post(route('monitors.store'), [
                'url'                           => 'https://example.com',
                'method'                        => 'GET',
                'interval_minutes'              => 5,
                'notification_cooldown_minutes' => $minutes,
            ])
            ->assertRedirect(route('dashboard'));
    }
});

// ─── Edit form includes cooldown values ───────────────────────────────────────

test('edit form passes notification cooldown fields from monitor', function () {
    $user    = User::factory()->create(['plan' => 'pro']);
    $monitor = Monitor::factory()->create([
        'user_id'                       => $user->id,
        'notification_cooldown_minutes' => 30,
        'recovery_bypass_cooldown'      => false,
    ]);

    $this->actingAs($user)
        ->get(route('monitors.edit', $monitor))
        ->assertInertia(fn ($page) => $page
            ->component('Monitors/Edit')
            ->where('monitor.notification_cooldown_minutes', 30)
            ->where('monitor.recovery_bypass_cooldown', false)
            ->where('notificationsConfigurable', true)
            ->has('cooldownOptions')
        );
});

// ─── Plan gating: cooldown configurability ────────────────────────────────────

test('free user cannot set notification_cooldown_minutes (prohibited)', function () {
    $user = User::factory()->create(['plan' => 'free']);

    $this->actingAs($user)
        ->post(route('monitors.store'), [
            'url'                           => 'https://example.com',
            'method'                        => 'GET',
            'interval_minutes'              => 5,
            'notification_cooldown_minutes' => 5,
        ])
        ->assertSessionHasErrors('notification_cooldown_minutes');
});

test('free user cannot set recovery_bypass_cooldown (prohibited)', function () {
    $user = User::factory()->create(['plan' => 'free']);

    $this->actingAs($user)
        ->post(route('monitors.store'), [
            'url'                       => 'https://example.com',
            'method'                    => 'GET',
            'interval_minutes'          => 5,
            'recovery_bypass_cooldown'  => false,
        ])
        ->assertSessionHasErrors('recovery_bypass_cooldown');
});

test('free user can create monitor without cooldown fields and gets defaults', function () {
    $user = User::factory()->create(['plan' => 'free']);

    $this->actingAs($user)
        ->post(route('monitors.store'), [
            'url'              => 'https://example.com',
            'method'           => 'GET',
            'interval_minutes' => 5,
        ])
        ->assertRedirect(route('dashboard'));

    $monitor = $user->monitors()->first();
    expect($monitor->notification_cooldown_minutes)->toBe(15)
        ->and($monitor->recovery_bypass_cooldown)->toBeTrue();
});

test('free user cannot update notification_cooldown_minutes on existing monitor', function () {
    $user    = User::factory()->create(['plan' => 'free']);
    $monitor = Monitor::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->put(route('monitors.update', $monitor), [
            'url'                           => $monitor->url,
            'interval_minutes'              => 5,
            'notification_cooldown_minutes' => 30,
        ])
        ->assertSessionHasErrors('notification_cooldown_minutes');
});

test('pro user can set custom notification_cooldown_minutes', function () {
    $user = User::factory()->create(['plan' => 'pro']);

    $this->actingAs($user)
        ->post(route('monitors.store'), [
            'url'                           => 'https://example.com',
            'method'                        => 'GET',
            'interval_minutes'              => 1,
            'notification_cooldown_minutes' => 30,
            'recovery_bypass_cooldown'      => false,
        ])
        ->assertRedirect(route('dashboard'));

    $monitor = $user->monitors()->first();
    expect($monitor->notification_cooldown_minutes)->toBe(30)
        ->and($monitor->recovery_bypass_cooldown)->toBeFalse();
});

test('pro user gets invalid error for non-allowed cooldown value', function () {
    $user = User::factory()->create(['plan' => 'pro']);

    $this->actingAs($user)
        ->post(route('monitors.store'), [
            'url'                           => 'https://example.com',
            'method'                        => 'GET',
            'interval_minutes'              => 1,
            'notification_cooldown_minutes' => 20, // not in [5,10,15,30,60]
        ])
        ->assertSessionHasErrors('notification_cooldown_minutes');
});

test('create form for free user has notificationsConfigurable false and empty cooldownOptions', function () {
    $user = User::factory()->create(['plan' => 'free']);

    $this->actingAs($user)
        ->get(route('monitors.create'))
        ->assertInertia(fn ($page) => $page
            ->component('Monitors/Create')
            ->where('notificationsConfigurable', false)
            ->where('cooldownOptions', [])
        );
});

test('create form for pro user has notificationsConfigurable true and full cooldownOptions', function () {
    $user = User::factory()->create(['plan' => 'pro']);

    $this->actingAs($user)
        ->get(route('monitors.create'))
        ->assertInertia(fn ($page) => $page
            ->component('Monitors/Create')
            ->where('notificationsConfigurable', true)
            ->where('cooldownOptions', [5, 10, 15, 30, 60])
        );
});

test('edit form for free user has notificationsConfigurable false', function () {
    $user    = User::factory()->create(['plan' => 'free']);
    $monitor = Monitor::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('monitors.edit', $monitor))
        ->assertInertia(fn ($page) => $page
            ->component('Monitors/Edit')
            ->where('notificationsConfigurable', false)
            ->where('cooldownOptions', [])
        );
});

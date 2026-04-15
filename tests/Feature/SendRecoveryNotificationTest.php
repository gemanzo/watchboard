<?php

use App\Events\MonitorStatusChanged;
use App\Listeners\SendRecoveryNotification;
use App\Models\CheckResult;
use App\Models\Monitor;
use App\Models\User;
use App\Notifications\MonitorRecoveredNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

// ─── Helper ───────────────────────────────────────────────────────────────────

function recoveryEvent(?int $downtimeSeconds = 600): MonitorStatusChanged
{
    $monitor = Monitor::factory()->create([
        'user_id' => User::factory(),
    ]);

    $checkResult = CheckResult::create([
        'monitor_id'       => $monitor->id,
        'status_code'      => 200,
        'response_time_ms' => 95,
        'is_successful'    => true,
        'checked_at'       => now(),
    ]);

    return new MonitorStatusChanged($monitor, 'down', 'up', $checkResult, $downtimeSeconds);
}

// ─── Notification sent ────────────────────────────────────────────────────────

test('sends recovery notification to monitor owner when monitor comes back up', function () {
    Notification::fake();

    $event = recoveryEvent();
    (new SendRecoveryNotification)->handle($event);

    Notification::assertSentTo($event->monitor->user, MonitorRecoveredNotification::class);
});

// ─── Notification not sent ────────────────────────────────────────────────────

test('does not send notification when monitor goes down (up → down)', function () {
    Notification::fake();

    $monitor = Monitor::factory()->create(['user_id' => User::factory()]);
    $checkResult = CheckResult::create([
        'monitor_id'       => $monitor->id,
        'status_code'      => 500,
        'response_time_ms' => 0,
        'is_successful'    => false,
        'checked_at'       => now(),
    ]);

    $event = new MonitorStatusChanged($monitor, 'up', 'down', $checkResult);
    (new SendRecoveryNotification)->handle($event);

    Notification::assertNothingSent();
});

test('does not send notification on unknown → up', function () {
    Notification::fake();

    $monitor = Monitor::factory()->create(['user_id' => User::factory()]);
    $checkResult = CheckResult::create([
        'monitor_id'       => $monitor->id,
        'status_code'      => 200,
        'response_time_ms' => 80,
        'is_successful'    => true,
        'checked_at'       => now(),
    ]);

    $event = new MonitorStatusChanged($monitor, 'unknown', 'up', $checkResult);
    (new SendRecoveryNotification)->handle($event);

    Notification::assertNothingSent();
});

// ─── Notification content ─────────────────────────────────────────────────────

test('notification mail contains monitor name, url, recovery timestamp and downtime', function () {
    Notification::fake();

    $event = recoveryEvent(downtimeSeconds: 3750); // 1h 2m 30s
    (new SendRecoveryNotification)->handle($event);

    Notification::assertSentTo(
        $event->monitor->user,
        MonitorRecoveredNotification::class,
        function (MonitorRecoveredNotification $notification) use ($event) {
            $mail = $notification->toMail($event->monitor->user);
            $rendered = implode(' ', $mail->introLines);

            return str_contains($mail->subject, $event->monitor->name)
                && str_contains($rendered, $event->monitor->url)
                && str_contains($rendered, $event->checkResult->checked_at->toDateTimeString())
                && str_contains($rendered, '1h 2m'); // downtime formattato
        }
    );
});

test('notification omits downtime line when downtimeSeconds is null', function () {
    Notification::fake();

    $event = recoveryEvent(downtimeSeconds: null);
    (new SendRecoveryNotification)->handle($event);

    Notification::assertSentTo(
        $event->monitor->user,
        MonitorRecoveredNotification::class,
        function (MonitorRecoveredNotification $notification) use ($event) {
            $mail = $notification->toMail($event->monitor->user);
            $rendered = implode(' ', $mail->introLines);

            return ! str_contains($rendered, 'Downtime');
        }
    );
});

// ─── Downtime formatting ──────────────────────────────────────────────────────

test('formats downtime in seconds when under a minute', function () {
    $notification = new MonitorRecoveredNotification(
        Monitor::factory()->make(),
        CheckResult::factory()->make(),
        45,
    );

    $mail = $notification->toMail(User::factory()->make());
    expect(implode(' ', $mail->introLines))->toContain('45s');
});

test('formats downtime in minutes when under an hour', function () {
    $notification = new MonitorRecoveredNotification(
        Monitor::factory()->make(),
        CheckResult::factory()->make(),
        150, // 2m 30s
    );

    $mail = $notification->toMail(User::factory()->make());
    expect(implode(' ', $mail->introLines))->toContain('2m 30s');
});

test('formats downtime in hours and minutes when over an hour', function () {
    $notification = new MonitorRecoveredNotification(
        Monitor::factory()->make(),
        CheckResult::factory()->make(),
        5400, // 1h 30m
    );

    $mail = $notification->toMail(User::factory()->make());
    expect(implode(' ', $mail->introLines))->toContain('1h 30m');
});

// ─── Queue ────────────────────────────────────────────────────────────────────

test('listener is queued on the notifications queue', function () {
    $listener = new SendRecoveryNotification();

    expect($listener)->toBeInstanceOf(ShouldQueue::class);
    expect($listener->queue)->toBe('notifications');
});

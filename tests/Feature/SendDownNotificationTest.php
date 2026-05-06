<?php

use App\Events\MonitorStatusChanged;
use App\Listeners\SendDownNotification;
use App\Models\CheckResult;
use App\Models\Monitor;
use App\Models\User;
use App\Notifications\MonitorDownNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

// ─── Helper ───────────────────────────────────────────────────────────────────

function makeEvent(string $oldStatus, string $newStatus, ?int $statusCode = 500): MonitorStatusChanged
{
    $monitor = Monitor::factory()->create([
        'user_id' => User::factory(),
    ]);

    $checkResult = CheckResult::create([
        'monitor_id'       => $monitor->id,
        'status_code'      => $statusCode,
        'response_time_ms' => 120,
        'is_successful'    => false,
        'checked_at'       => now(),
    ]);

    return new MonitorStatusChanged($monitor, $oldStatus, $newStatus, $checkResult);
}

// ─── Notification sent ────────────────────────────────────────────────────────

test('sends notification to monitor owner when status goes down', function () {
    Notification::fake();

    $event = makeEvent('up', 'down');
    (new SendDownNotification(new \App\Services\NotificationThrottler()))->handle($event);

    Notification::assertSentTo($event->monitor->user, MonitorDownNotification::class);
});

test('sends notification on first check when service is already down (unknown → down)', function () {
    Notification::fake();

    $event = makeEvent('unknown', 'down');
    (new SendDownNotification(new \App\Services\NotificationThrottler()))->handle($event);

    Notification::assertSentTo($event->monitor->user, MonitorDownNotification::class);
});

// ─── Notification not sent ────────────────────────────────────────────────────

test('does not send notification when monitor recovers (down → up)', function () {
    Notification::fake();

    $event = makeEvent('down', 'up');
    (new SendDownNotification(new \App\Services\NotificationThrottler()))->handle($event);

    Notification::assertNothingSent();
});

// ─── Notification content ─────────────────────────────────────────────────────

test('notification mail contains monitor name, url, status code and timestamp', function () {
    Notification::fake();

    $monitor = Monitor::factory()->create([
        'user_id' => User::factory(),
        'name'    => 'My API',
        'url'     => 'https://api.example.com',
    ]);

    $checkResult = CheckResult::create([
        'monitor_id'       => $monitor->id,
        'status_code'      => 503,
        'response_time_ms' => 200,
        'is_successful'    => false,
        'checked_at'       => now(),
    ]);

    $event = new MonitorStatusChanged($monitor, 'up', 'down', $checkResult);
    (new SendDownNotification(new \App\Services\NotificationThrottler()))->handle($event);

    Notification::assertSentTo(
        $monitor->user,
        MonitorDownNotification::class,
        function (MonitorDownNotification $notification) use ($monitor, $checkResult) {
            $mail = $notification->toMail($monitor->user);
            $rendered = implode(' ', array_column($mail->introLines, 'line') + $mail->introLines);

            return str_contains($mail->subject, $monitor->name)
                && str_contains($rendered, $monitor->url)
                && str_contains($rendered, '503')
                && str_contains($rendered, $checkResult->checked_at->toDateTimeString());
        }
    );
});

test('notification shows "Connection failed" when status code is null', function () {
    Notification::fake();

    $monitor = Monitor::factory()->create(['user_id' => User::factory()]);

    $checkResult = CheckResult::create([
        'monitor_id'       => $monitor->id,
        'status_code'      => null,
        'response_time_ms' => 0,
        'is_successful'    => false,
        'checked_at'       => now(),
    ]);

    $event = new MonitorStatusChanged($monitor, 'up', 'down', $checkResult);
    (new SendDownNotification(new \App\Services\NotificationThrottler()))->handle($event);

    Notification::assertSentTo(
        $monitor->user,
        MonitorDownNotification::class,
        function (MonitorDownNotification $notification) use ($monitor) {
            $mail = $notification->toMail($monitor->user);
            $rendered = implode(' ', $mail->introLines);

            return str_contains($rendered, 'Connection failed');
        }
    );
});

// ─── Queue ────────────────────────────────────────────────────────────────────

test('listener is queued on the notifications queue', function () {
    $listener = new SendDownNotification(new \App\Services\NotificationThrottler());

    expect($listener)->toBeInstanceOf(Illuminate\Contracts\Queue\ShouldQueue::class);
    expect($listener->queue)->toBe('notifications');
});

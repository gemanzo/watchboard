<?php

namespace App\Notifications\Channels;

use App\Models\CheckResult;
use App\Models\Monitor;
use App\Models\NotificationChannel;
use App\Notifications\ChannelTestNotification;
use App\Notifications\MonitorDownNotification;
use App\Notifications\MonitorRecoveredNotification;
use App\Notifications\MonitorSlowResponseNotification;
use Illuminate\Support\Facades\Notification;

class EmailChannelHandler implements ChannelHandler
{
    public function send(NotificationChannel $channel, string $event, array $context): void
    {
        $address     = $channel->config['address'];
        $monitor     = $context['monitor'];
        $checkResult = $context['check_result'] ?? null;

        $notification = match ($event) {
            'monitor.down'          => $checkResult
                ? new MonitorDownNotification($monitor, $checkResult)
                : null,
            'monitor.recovered'     => $checkResult
                ? new MonitorRecoveredNotification($monitor, $checkResult, $context['downtime_seconds'] ?? null)
                : null,
            'monitor.slow_response' => $checkResult
                ? new MonitorSlowResponseNotification($monitor, $checkResult, $context['threshold_ms'] ?? 0)
                : null,
            default => null,
        };

        if ($notification === null) {
            return;
        }

        // notifyNow bypasses ShouldQueue — the listener is already running in
        // the 'notifications' queue, so we send synchronously here to avoid
        // double-queuing and to keep timeout/failure handling in one place.
        Notification::route('mail', $address)->notifyNow($notification);
    }

    public function sendTest(NotificationChannel $channel): void
    {
        $address = $channel->config['address'];

        Notification::route('mail', $address)->notify(new ChannelTestNotification());
    }
}

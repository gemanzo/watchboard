<?php

namespace App\Listeners;

use App\Events\MonitorStatusChanged;
use App\Notifications\MonitorRecoveredNotification;
use App\Services\NotificationThrottler;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendRecoveryNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'notifications';

    public function __construct(private readonly NotificationThrottler $throttler) {}

    public function handle(MonitorStatusChanged $event): void
    {
        if ($event->oldStatus !== 'down' || $event->newStatus !== 'up') {
            return;
        }

        if (! $this->throttler->shouldSend($event->monitor, isRecovery: true)) {
            return;
        }

        $event->monitor->user->notify(new MonitorRecoveredNotification(
            $event->monitor,
            $event->checkResult,
            $event->downtimeSeconds,
        ));

        $this->throttler->recordNotificationSent($event->monitor);
    }
}

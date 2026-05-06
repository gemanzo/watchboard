<?php

namespace App\Listeners;

use App\Events\MonitorStatusChanged;
use App\Notifications\MonitorDownNotification;
use App\Services\NotificationThrottler;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendDownNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'notifications';

    public function __construct(private readonly NotificationThrottler $throttler) {}

    public function handle(MonitorStatusChanged $event): void
    {
        if ($event->newStatus !== 'down') {
            return;
        }

        if (! $this->throttler->shouldSend($event->monitor)) {
            return;
        }

        $event->monitor->user->notify(new MonitorDownNotification(
            $event->monitor,
            $event->checkResult,
        ));

        $this->throttler->recordNotificationSent($event->monitor);
    }
}

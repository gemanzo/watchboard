<?php

namespace App\Listeners;

use App\Events\MonitorSlowResponse;
use App\Notifications\MonitorSlowResponseNotification;
use App\Services\NotificationThrottler;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendSlowResponseNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'notifications';

    public function __construct(private readonly NotificationThrottler $throttler) {}

    public function handle(MonitorSlowResponse $event): void
    {
        if (! $this->throttler->shouldSend($event->monitor)) {
            return;
        }

        $event->monitor->user->notify(new MonitorSlowResponseNotification(
            $event->monitor,
            $event->checkResult,
            $event->thresholdMs,
        ));

        $this->throttler->recordNotificationSent($event->monitor);
    }
}

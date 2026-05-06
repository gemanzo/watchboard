<?php

namespace App\Listeners;

use App\Events\MonitorSlowResponse;
use App\Notifications\MonitorSlowResponseNotification;
use App\Services\NotificationDispatcher;
use App\Services\NotificationThrottler;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendSlowResponseNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'notifications';

    private readonly NotificationDispatcher $dispatcher;

    public function __construct(
        private readonly NotificationThrottler $throttler,
        ?NotificationDispatcher $dispatcher = null,
    ) {
        $this->dispatcher = $dispatcher ?? new NotificationDispatcher();
    }

    public function handle(MonitorSlowResponse $event): void
    {
        if (! $this->throttler->shouldSend($event->monitor)) {
            return;
        }

        // Primary email (user's account address)
        $event->monitor->user->notify(new MonitorSlowResponseNotification(
            $event->monitor,
            $event->checkResult,
            $event->thresholdMs,
        ));

        // Additional channels
        $this->dispatcher->dispatch($event->monitor->user, 'monitor.slow_response', [
            'monitor'      => $event->monitor,
            'check_result' => $event->checkResult,
            'threshold_ms' => $event->thresholdMs,
        ]);

        $this->throttler->recordNotificationSent($event->monitor);
    }
}

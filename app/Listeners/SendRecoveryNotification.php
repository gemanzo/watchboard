<?php

namespace App\Listeners;

use App\Events\MonitorStatusChanged;
use App\Notifications\MonitorRecoveredNotification;
use App\Services\NotificationDispatcher;
use App\Services\NotificationThrottler;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendRecoveryNotification implements ShouldQueue
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

    public function handle(MonitorStatusChanged $event): void
    {
        if ($event->oldStatus !== 'down' || $event->newStatus !== 'up') {
            return;
        }

        if (! $this->throttler->shouldSend($event->monitor, isRecovery: true)) {
            return;
        }

        // Primary email (user's account address)
        $event->monitor->user->notify(new MonitorRecoveredNotification(
            $event->monitor,
            $event->checkResult,
            $event->downtimeSeconds,
        ));

        // Additional channels
        $this->dispatcher->dispatch($event->monitor->user, 'monitor.recovered', [
            'monitor'          => $event->monitor,
            'check_result'     => $event->checkResult,
            'downtime_seconds' => $event->downtimeSeconds,
        ]);

        $this->throttler->recordNotificationSent($event->monitor);
    }
}

<?php

namespace App\Listeners;

use App\Events\MonitorStatusChanged;
use App\Notifications\MonitorDownNotification;
use App\Services\NotificationDispatcher;
use App\Services\NotificationThrottler;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendDownNotification implements ShouldQueue
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
        if ($event->newStatus !== 'down') {
            return;
        }

        if (! $this->throttler->shouldSend($event->monitor)) {
            return;
        }

        // Primary email (user's account address)
        $event->monitor->user->notify(new MonitorDownNotification(
            $event->monitor,
            $event->checkResult,
        ));

        // Additional channels (webhook, slack, custom email)
        $this->dispatcher->dispatch($event->monitor->user, 'monitor.down', [
            'monitor'      => $event->monitor,
            'check_result' => $event->checkResult,
        ]);

        $this->throttler->recordNotificationSent($event->monitor);
    }
}

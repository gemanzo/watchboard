<?php

namespace App\Listeners;

use App\Events\MonitorStatusChanged;
use App\Notifications\MonitorDownNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendDownNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'notifications';

    public function handle(MonitorStatusChanged $event): void
    {
        if ($event->newStatus !== 'down') {
            return;
        }

        $event->monitor->user->notify(new MonitorDownNotification(
            $event->monitor,
            $event->checkResult,
        ));
    }
}

<?php

namespace App\Listeners;

use App\Events\MonitorSlowResponse;
use App\Notifications\MonitorSlowResponseNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendSlowResponseNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'notifications';

    public function handle(MonitorSlowResponse $event): void
    {
        $event->monitor->user->notify(new MonitorSlowResponseNotification(
            $event->monitor,
            $event->checkResult,
            $event->thresholdMs,
        ));
    }
}

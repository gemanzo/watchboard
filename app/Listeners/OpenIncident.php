<?php

namespace App\Listeners;

use App\Events\MonitorStatusChanged;
use App\Models\Incident;

class OpenIncident
{
    public function handle(MonitorStatusChanged $event): void
    {
        if ($event->newStatus !== 'down') {
            return;
        }

        Incident::create([
            'monitor_id' => $event->monitor->id,
            'started_at' => $event->checkResult->checked_at,
        ]);
    }
}

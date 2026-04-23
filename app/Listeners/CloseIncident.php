<?php

namespace App\Listeners;

use App\Events\MonitorStatusChanged;
use App\Models\Incident;

class CloseIncident
{
    public function handle(MonitorStatusChanged $event): void
    {
        if ($event->oldStatus !== 'down' || $event->newStatus !== 'up') {
            return;
        }

        $incident = Incident::where('monitor_id', $event->monitor->id)
            ->whereNull('resolved_at')
            ->latest('started_at')
            ->first();

        if ($incident === null) {
            return;
        }

        $resolvedAt = $event->checkResult->checked_at;

        $incident->update([
            'resolved_at'      => $resolvedAt,
            'duration_seconds' => (int) $incident->started_at->diffInSeconds($resolvedAt),
        ]);
    }
}

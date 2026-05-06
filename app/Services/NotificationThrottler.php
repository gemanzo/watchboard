<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Monitor;

class NotificationThrottler
{
    /**
     * Determine whether a notification should be sent for the given monitor.
     *
     * Recovery notifications bypass the cooldown when the monitor is configured
     * to allow it (recovery_bypass_cooldown = true). All other notification types
     * respect the cooldown window unconditionally.
     */
    public function shouldSend(Monitor $monitor, bool $isRecovery = false): bool
    {
        if ($isRecovery && $monitor->recovery_bypass_cooldown) {
            return true;
        }

        if ($monitor->last_notified_at === null) {
            return true;
        }

        return $monitor->last_notified_at
            ->copy()
            ->addMinutes($monitor->notification_cooldown_minutes)
            ->isPast();
    }

    /**
     * Record that a notification was just sent for this monitor.
     * Updates last_notified_at to now so subsequent cooldown checks are accurate.
     */
    public function recordNotificationSent(Monitor $monitor): void
    {
        $monitor->update(['last_notified_at' => now()]);
    }
}

<?php

namespace App\Notifications;

use App\Models\CheckResult;
use App\Models\Monitor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MonitorSlowResponseNotification extends Notification implements ShouldQueue
{
    public function __construct(
        public readonly Monitor     $monitor,
        public readonly CheckResult $checkResult,
        public readonly int         $thresholdMs,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail']; // estendibile: ['mail', 'slack', 'vonage', ...]
    }

    public function viaQueues(): array
    {
        return ['mail' => 'notifications'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $responseTime = number_format($this->checkResult->response_time_ms / 1000, 2);
        $threshold    = number_format($this->thresholdMs / 1000, 2);

        return (new MailMessage)
            ->subject("[WatchBoard] {$this->monitor->name} is responding slowly")
            ->greeting('Slow Response Alert')
            ->line("**{$this->monitor->name}** is responding slower than expected.")
            ->line("**URL:** {$this->monitor->url}")
            ->line("**Response time:** {$responseTime}s (threshold: {$threshold}s)")
            ->line("**Detected at:** {$this->checkResult->checked_at->toDateTimeString()} UTC")
            ->salutation('— WatchBoard');
    }
}

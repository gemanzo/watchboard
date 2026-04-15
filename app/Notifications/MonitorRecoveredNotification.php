<?php

namespace App\Notifications;

use App\Models\CheckResult;
use App\Models\Monitor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MonitorRecoveredNotification extends Notification implements ShouldQueue
{
    public function __construct(
        public readonly Monitor $monitor,
        public readonly CheckResult $checkResult,
        public readonly ?int $downtimeSeconds,
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
        $message = (new MailMessage)
            ->subject("[WatchBoard] {$this->monitor->name} is back up")
            ->greeting('Monitor Recovery')
            ->line("**{$this->monitor->name}** is responding again.")
            ->line("**URL:** {$this->monitor->url}");

        if ($this->downtimeSeconds !== null) {
            $message->line("**Downtime:** {$this->formatDowntime($this->downtimeSeconds)}");
        }

        return $message
            ->line("**Recovered at:** {$this->checkResult->checked_at->toDateTimeString()} UTC")
            ->salutation('— WatchBoard');
    }

    private function formatDowntime(int $seconds): string
    {
        if ($seconds < 60) {
            return "{$seconds}s";
        }

        $minutes = intdiv($seconds, 60);

        if ($minutes < 60) {
            $remaining = $seconds % 60;
            return $remaining > 0 ? "{$minutes}m {$remaining}s" : "{$minutes}m";
        }

        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;

        return $remaining > 0 ? "{$hours}h {$remaining}m" : "{$hours}h";
    }
}

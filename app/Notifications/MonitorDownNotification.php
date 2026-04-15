<?php

namespace App\Notifications;

use App\Models\CheckResult;
use App\Models\Monitor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MonitorDownNotification extends Notification implements ShouldQueue
{
    public function __construct(
        public readonly Monitor $monitor,
        public readonly CheckResult $checkResult,
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
        $statusCode = $this->checkResult->status_code !== null
            ? (string) $this->checkResult->status_code
            : 'Connection failed';

        return (new MailMessage)
            ->subject("[WatchBoard] {$this->monitor->name} is down")
            ->greeting('Monitor Down Alert')
            ->line("**{$this->monitor->name}** is not responding.")
            ->line("**URL:** {$this->monitor->url}")
            ->line("**Status:** {$statusCode}")
            ->line("**Detected at:** {$this->checkResult->checked_at->toDateTimeString()} UTC")
            ->salutation('— WatchBoard');
    }
}

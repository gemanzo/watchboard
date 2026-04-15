<?php

namespace App\Notifications;

use App\Models\CheckResult;
use App\Models\Monitor;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MonitorDownNotification extends Notification
{
    public function __construct(
        public readonly Monitor $monitor,
        public readonly CheckResult $checkResult,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
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

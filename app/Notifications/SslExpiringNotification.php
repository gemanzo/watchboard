<?php

namespace App\Notifications;

use App\Models\Monitor;
use App\Models\SslCheck;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SslExpiringNotification extends Notification implements ShouldQueue
{
    public function __construct(
        public readonly Monitor  $monitor,
        public readonly SslCheck $sslCheck,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function viaQueues(): array
    {
        return ['mail' => 'notifications'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $alertLevel = $this->sslCheck->alertLevel();

        return match ($alertLevel) {
            'expired'  => $this->expiredMail(),
            'critical' => $this->criticalMail(),
            default    => $this->warningMail(),
        };
    }

    private function displayName(): string
    {
        return $this->monitor->name ?? $this->monitor->url;
    }

    private function expiredMail(): MailMessage
    {
        $name = $this->displayName();

        return (new MailMessage)
            ->subject("[WatchBoard] SSL certificate EXPIRED — {$name}")
            ->greeting('SSL Certificate Expired')
            ->line("The SSL certificate for **{$name}** has expired or is invalid.")
            ->line("**URL:** {$this->monitor->url}")
            ->line($this->sslCheck->error
                ? "**Error:** {$this->sslCheck->error}"
                : "**Expired on:** {$this->sslCheck->valid_to?->toDateString()}")
            ->line('Visitors will see a security warning. Renew the certificate immediately.')
            ->salutation('— WatchBoard');
    }

    private function criticalMail(): MailMessage
    {
        $name = $this->displayName();
        $days = $this->sslCheck->days_until_expiry;

        return (new MailMessage)
            ->subject("[WatchBoard] SSL expiring in {$days}d — {$name}")
            ->greeting('SSL Certificate Expiring Soon (Critical)')
            ->line("The SSL certificate for **{$name}** expires in **{$days} days**.")
            ->line("**URL:** {$this->monitor->url}")
            ->line("**Issuer:** " . ($this->sslCheck->issuer ?? '—'))
            ->line("**Expires on:** {$this->sslCheck->valid_to?->toDateString()}")
            ->line('Renew the certificate as soon as possible to avoid service disruption.')
            ->salutation('— WatchBoard');
    }

    private function warningMail(): MailMessage
    {
        $name = $this->displayName();
        $days = $this->sslCheck->days_until_expiry;

        return (new MailMessage)
            ->subject("[WatchBoard] SSL expiring in {$days}d — {$name}")
            ->greeting('SSL Certificate Expiring Soon')
            ->line("The SSL certificate for **{$name}** expires in **{$days} days**.")
            ->line("**URL:** {$this->monitor->url}")
            ->line("**Issuer:** " . ($this->sslCheck->issuer ?? '—'))
            ->line("**Expires on:** {$this->sslCheck->valid_to?->toDateString()}")
            ->line('Plan your renewal to avoid any disruption.')
            ->salutation('— WatchBoard');
    }
}

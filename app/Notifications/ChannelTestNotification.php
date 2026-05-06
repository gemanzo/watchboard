<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ChannelTestNotification extends Notification
{
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('[WatchBoard] Test Notification')
            ->greeting('Test Alert')
            ->line('This is a test notification from WatchBoard.')
            ->line('If you received this, your email channel is configured correctly.')
            ->salutation('— WatchBoard');
    }
}

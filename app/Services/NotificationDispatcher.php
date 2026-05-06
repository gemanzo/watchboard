<?php

namespace App\Services;

use App\Models\NotificationChannel;
use App\Models\User;
use App\Notifications\Channels\ChannelHandler;
use App\Notifications\Channels\EmailChannelHandler;
use App\Notifications\Channels\SlackChannelHandler;
use App\Notifications\Channels\WebhookChannelHandler;
use Illuminate\Support\Facades\Log;

class NotificationDispatcher
{
    /**
     * Dispatch a notification event to all active channels of the given user.
     */
    public function dispatch(User $user, string $event, array $context): void
    {
        $user->notificationChannels()
            ->where('is_active', true)
            ->each(function (NotificationChannel $channel) use ($event, $context) {
                $this->tryChannel($channel, $event, $context);
            });
    }

    /**
     * Send a test payload to a single channel.
     *
     * @return array{success: bool, message: string}
     */
    public function dispatchTest(NotificationChannel $channel): array
    {
        try {
            $this->resolveHandler($channel->type)->sendTest($channel);

            return ['success' => true, 'message' => 'Test inviato con successo.'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // -------------------------------------------------------------------------

    private function tryChannel(NotificationChannel $channel, string $event, array $context): void
    {
        try {
            $this->resolveHandler($channel->type)->send($channel, $event, $context);
        } catch (\Throwable $e) {
            Log::warning('Notification channel dispatch failed', [
                'channel_id' => $channel->id,
                'type'       => $channel->type,
                'event'      => $event,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    private function resolveHandler(string $type): ChannelHandler
    {
        return match ($type) {
            'webhook' => new WebhookChannelHandler(),
            'slack'   => new SlackChannelHandler(),
            'email'   => new EmailChannelHandler(),
            default   => throw new \InvalidArgumentException("Unknown channel type: {$type}"),
        };
    }
}

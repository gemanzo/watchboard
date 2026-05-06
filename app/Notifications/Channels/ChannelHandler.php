<?php

namespace App\Notifications\Channels;

use App\Models\NotificationChannel;

interface ChannelHandler
{
    /**
     * Send a notification event to the channel.
     *
     * @param  array{monitor: object, check_result: object|null, downtime_seconds?: int|null, threshold_ms?: int|null}  $context
     */
    public function send(NotificationChannel $channel, string $event, array $context): void;

    /**
     * Send a test payload to verify the channel is reachable.
     */
    public function sendTest(NotificationChannel $channel): void;
}

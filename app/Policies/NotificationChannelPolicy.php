<?php

namespace App\Policies;

use App\Models\NotificationChannel;
use App\Models\User;

class NotificationChannelPolicy
{
    public function update(User $user, NotificationChannel $channel): bool
    {
        return $user->id === $channel->user_id;
    }

    public function delete(User $user, NotificationChannel $channel): bool
    {
        return $user->id === $channel->user_id;
    }
}

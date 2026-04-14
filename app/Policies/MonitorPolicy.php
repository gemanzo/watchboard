<?php

namespace App\Policies;

use App\Models\Monitor;
use App\Models\User;

class MonitorPolicy
{
    public function create(User $user): bool
    {
        return $user->monitors()->count() < $user->planConfig()['max_monitors'];
    }

    public function view(User $user, Monitor $monitor): bool
    {
        return $user->id === $monitor->user_id;
    }

    public function update(User $user, Monitor $monitor): bool
    {
        return $user->id === $monitor->user_id;
    }

    public function delete(User $user, Monitor $monitor): bool
    {
        return $user->id === $monitor->user_id;
    }
}

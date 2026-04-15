<?php

namespace App\Policies;

use App\Models\Monitor;
use App\Models\User;

class MonitorPolicy
{
    public function create(User $user): bool
    {
        $maxMonitors = $user->planConfig()['max_monitors'];

        if ($maxMonitors === null) {
            return true;
        }

        return $user->monitors()->count() < $maxMonitors;
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

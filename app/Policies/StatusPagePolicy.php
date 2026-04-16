<?php

namespace App\Policies;

use App\Models\StatusPage;
use App\Models\User;

class StatusPagePolicy
{
    public function create(User $user): bool
    {
        $max = $user->planConfig()['max_status_pages'];

        if ($max === null) {
            return true;
        }

        return $user->statusPages()->count() < $max;
    }

    public function view(User $user, StatusPage $statusPage): bool
    {
        return $user->id === $statusPage->user_id;
    }

    public function update(User $user, StatusPage $statusPage): bool
    {
        return $user->id === $statusPage->user_id;
    }

    public function delete(User $user, StatusPage $statusPage): bool
    {
        return $user->id === $statusPage->user_id;
    }
}

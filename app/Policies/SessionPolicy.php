<?php

namespace App\Policies;

use App\Models\User;

class SessionPolicy
{
    public function delete(User $user, object $session): bool
    {
        return $user->id === $session->user_id;
    }
}

<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class PersonalApiTokenPolicy
{
    public function managePersonalApiTokens(User $user, User $subject): bool
    {
        return $user->is_active
            && $user->id === $subject->id
            && $user->organization_id !== null
            && $user->role !== UserRole::Client;
    }
}

<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class TeamMemberPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->organization_id !== null && $user->role === UserRole::AgencyOwner;
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function viewActivity(User $user): bool
    {
        return $user->is_active
            && $user->organization_id !== null
            && in_array($user->role, [UserRole::AgencyOwner, UserRole::MarketingManager, UserRole::Professional], true);
    }
}

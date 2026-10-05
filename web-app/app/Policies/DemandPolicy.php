<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Demand;
use App\Models\User;

class DemandPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->organization_id !== null;
    }

    public function view(User $user, Demand $demand): bool
    {
        if (! $user->is_active || $user->organization_id !== $demand->organization_id) {
            return false;
        }

        if ($user->role === UserRole::Client) {
            return $demand->client_user_id === $user->id;
        }

        if (in_array($user->role, [UserRole::AgencyOwner, UserRole::MarketingManager], true)) {
            return true;
        }

        return $demand->created_by === $user->id || $demand->responsible_user_id === $user->id || $demand->tasks()->where('assigned_to', $user->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->organization_id !== null
            && in_array($user->role, [UserRole::AgencyOwner, UserRole::MarketingManager], true);
    }

    public function manage(User $user, Demand $demand): bool
    {
        return $this->create($user) && $user->organization_id === $demand->organization_id;
    }
}

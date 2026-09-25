<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\DemandTask;
use App\Models\User;

class DemandTaskPolicy
{
    public function trackTime(User $user, DemandTask $task): bool
    {
        return $user->organization_id === $task->organization_id
            && $user->role === UserRole::Professional
            && $user->id === $task->assigned_to
            && $user->is_active;
    }

    public function updateStatus(User $user, DemandTask $task): bool
    {
        if (! $user->is_active || $user->organization_id !== $task->organization_id) {
            return false;
        }

        return $user->role === UserRole::AgencyOwner
            || $user->role === UserRole::MarketingManager
            || ($user->role === UserRole::Professional && $task->assigned_to === $user->id);
    }
}

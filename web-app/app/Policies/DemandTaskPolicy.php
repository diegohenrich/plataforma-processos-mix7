<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\DemandTask;
use App\Models\User;

class DemandTaskPolicy
{
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

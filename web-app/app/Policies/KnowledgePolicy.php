<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\KnowledgeItem;
use App\Models\User;

class KnowledgePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->organization_id !== null && $user->role !== UserRole::Client;
    }

    public function view(User $user, KnowledgeItem $item): bool
    {
        return $this->viewAny($user) && $user->organization_id === $item->organization_id;
    }

    public function manageAny(User $user): bool
    {
        return $user->is_active && $user->organization_id !== null && in_array($user->role, [UserRole::AgencyOwner, UserRole::MarketingManager], true);
    }

    public function manage(User $user, KnowledgeItem $item): bool
    {
        return $this->manageAny($user) && $user->organization_id === $item->organization_id;
    }
}

<?php

namespace App\Policies;

use App\Models\CriterionWeightVersion;
use App\Models\User;

class CriterionWeightVersionPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->is_active && $actor->hasRole(User::ROLE_OFFICER, User::ROLE_EXPERT, User::ROLE_ADMIN);
    }

    public function view(User $actor, CriterionWeightVersion $version): bool
    {
        return $actor->is_active && $actor->hasRole(User::ROLE_OFFICER, User::ROLE_EXPERT, User::ROLE_ADMIN);
    }

    public function create(User $actor): bool
    {
        return $actor->is_active && $actor->hasRole(User::ROLE_EXPERT, User::ROLE_ADMIN);
    }

    public function calculate(User $actor, CriterionWeightVersion $version): bool
    {
        return $actor->is_active && $actor->hasRole(User::ROLE_EXPERT, User::ROLE_ADMIN);
    }

    public function approve(User $actor, CriterionWeightVersion $version): bool
    {
        return $actor->is_active && $actor->isAdmin();
    }
}

<?php

namespace App\Policies;

use App\Models\CriterionWeightVersion;
use App\Models\User;

class CriterionWeightVersionPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasRole(User::ROLE_OFFICER, User::ROLE_EXPERT, User::ROLE_ADMIN);
    }

    public function create(User $actor): bool
    {
        return $actor->isAdmin();
    }

    public function approve(User $actor, CriterionWeightVersion $version): bool
    {
        return $actor->isAdmin();
    }
}


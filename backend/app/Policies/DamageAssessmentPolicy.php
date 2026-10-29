<?php

namespace App\Policies;

use App\Models\DamageAssessment;
use App\Models\User;

class DamageAssessmentPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->is_active && $actor->hasRole(User::ROLE_OFFICER, User::ROLE_EXPERT, User::ROLE_ADMIN);
    }

    public function view(User $actor, DamageAssessment $assessment): bool
    {
        return $actor->is_active && $actor->hasRole(User::ROLE_OFFICER, User::ROLE_EXPERT, User::ROLE_ADMIN);
    }

    public function create(User $actor): bool
    {
        return $actor->is_active && $actor->hasRole(User::ROLE_OFFICER, User::ROLE_ADMIN);
    }
}


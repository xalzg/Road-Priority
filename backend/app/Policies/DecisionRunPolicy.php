<?php

namespace App\Policies;

use App\Models\DecisionRun;
use App\Models\User;

class DecisionRunPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->is_active && $actor->hasRole(User::ROLE_OFFICER, User::ROLE_EXPERT, User::ROLE_ADMIN);
    }

    public function view(User $actor, DecisionRun $run): bool
    {
        return $actor->is_active && $actor->hasRole(User::ROLE_OFFICER, User::ROLE_EXPERT, User::ROLE_ADMIN);
    }

    public function create(User $actor): bool
    {
        return $actor->is_active && $actor->hasRole(User::ROLE_OFFICER, User::ROLE_ADMIN);
    }
}

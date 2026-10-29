<?php

namespace App\Policies;

use App\Models\DecisionCriterion;
use App\Models\User;

class DecisionCriterionPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->is_active;
    }

    public function view(User $actor, DecisionCriterion $criterion): bool
    {
        return $actor->is_active;
    }

    public function create(User $actor): bool
    {
        return $actor->isAdmin();
    }

    public function update(User $actor, DecisionCriterion $criterion): bool
    {
        return $actor->isAdmin();
    }
}


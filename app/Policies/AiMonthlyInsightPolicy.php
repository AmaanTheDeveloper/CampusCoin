<?php

namespace App\Policies;

use App\Models\AiMonthlyInsight;
use App\Models\User;

class AiMonthlyInsightPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, AiMonthlyInsight $insight): bool
    {
        return $user->id === $insight->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, AiMonthlyInsight $insight): bool
    {
        return $user->id === $insight->user_id;
    }
}

<?php

namespace App\Policies;

use App\Models\SavingTip;
use App\Models\User;

class SavingTipPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, SavingTip $tip): bool
    {
        return $user->id === $tip->user_id;
    }

    public function update(User $user, SavingTip $tip): bool
    {
        return $user->id === $tip->user_id;
    }
}

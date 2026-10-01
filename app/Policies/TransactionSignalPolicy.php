<?php

namespace App\Policies;

use App\Models\TransactionSignal;
use App\Models\User;

class TransactionSignalPolicy
{
    public function view(User $user, TransactionSignal $signal): bool
    {
        return $signal->user_id === $user->id;
    }

    public function update(User $user, TransactionSignal $signal): bool
    {
        return $signal->user_id === $user->id;
    }
}

<?php

namespace App\Models;

use App\Enums\Loans\BankStaffRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankMembership extends Model
{
    protected $fillable = [
        'bank_id',
        'user_id',
        'staff_role',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'staff_role' => BankStaffRole::class,
            'is_active' => 'boolean',
        ];
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

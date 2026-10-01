<?php

namespace App\Models;

use App\Enums\Loans\BankStaffRole;
use App\Enums\Loans\BankStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bank extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'reference_code',
        'official_email',
        'phone',
        'address',
        'city',
        'country',
        'registration_number',
        'authorized_representative',
        'representative_email',
        'representative_phone',
        'status',
        'rejection_reason',
        'approved_by',
        'approved_at',
        'suspended_at',
        'suspension_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => BankStatus::class,
            'approved_at' => 'datetime',
            'suspended_at' => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === BankStatus::Approved && $this->suspended_at === null;
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(BankMembership::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'bank_memberships')
            ->withPivot(['staff_role', 'is_active'])
            ->withTimestamps();
    }

    public function products(): HasMany
    {
        return $this->hasMany(LoanProduct::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(LoanApplication::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function verificationDocuments(): HasMany
    {
        return $this->hasMany(BankVerificationDocument::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}

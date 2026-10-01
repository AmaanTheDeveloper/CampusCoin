<?php

namespace App\Models;

use App\Enums\Loans\InstallmentStatus;
use App\Support\MoneyMath;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoanInstallment extends Model
{
    protected $fillable = [
        'loan_id',
        'installment_number',
        'due_date',
        'amount_due',
        'principal_component',
        'interest_component',
        'amount_paid',
        'status',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'amount_due' => 'decimal:2',
            'principal_component' => 'decimal:2',
            'interest_component' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'status' => InstallmentStatus::class,
            'paid_at' => 'datetime',
        ];
    }

    public function remainingAmount(): string
    {
        return MoneyMath::sub((string) $this->amount_due, (string) $this->amount_paid);
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(LoanPayment::class);
    }
}

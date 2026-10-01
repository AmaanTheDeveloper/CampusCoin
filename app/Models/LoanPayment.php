<?php

namespace App\Models;

use App\Enums\Loans\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoanPayment extends Model
{
    protected $fillable = [
        'reference',
        'loan_id',
        'loan_installment_id',
        'user_id',
        'bank_id',
        'amount',
        'currency',
        'status',
        'provider',
        'provider_transaction_id',
        'payment_method_type',
        'card_last_four',
        'transaction_id',
        'paid_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => PaymentStatus::class,
            'paid_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function installment(): BelongsTo
    {
        return $this->belongsTo(LoanInstallment::class, 'loan_installment_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function financeTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(LoanPaymentEvent::class);
    }
}

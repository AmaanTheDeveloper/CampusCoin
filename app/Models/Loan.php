<?php

namespace App\Models;

use App\Enums\Loans\LoanStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Loan extends Model
{
    protected $fillable = [
        'reference',
        'loan_application_id',
        'user_id',
        'bank_id',
        'loan_product_id',
        'principal_amount',
        'tenure_months',
        'annual_rate_percent',
        'installment_amount',
        'total_repayable',
        'outstanding_balance',
        'status',
        'agreement_accepted_at',
        'disbursed_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'principal_amount' => 'decimal:2',
            'annual_rate_percent' => 'decimal:4',
            'installment_amount' => 'decimal:2',
            'total_repayable' => 'decimal:2',
            'outstanding_balance' => 'decimal:2',
            'status' => LoanStatus::class,
            'agreement_accepted_at' => 'datetime',
            'disbursed_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(LoanApplication::class, 'loan_application_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(LoanProduct::class, 'loan_product_id');
    }

    public function installments(): HasMany
    {
        return $this->hasMany(LoanInstallment::class)->orderBy('installment_number');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(LoanPayment::class);
    }

    public function disbursement(): HasOne
    {
        return $this->hasOne(LoanDisbursement::class);
    }
}

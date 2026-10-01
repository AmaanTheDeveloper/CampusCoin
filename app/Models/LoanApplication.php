<?php

namespace App\Models;

use App\Enums\Loans\LoanApplicationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LoanApplication extends Model
{
    protected $fillable = [
        'reference',
        'user_id',
        'bank_id',
        'loan_product_id',
        'requested_amount',
        'requested_tenure_months',
        'purpose',
        'applicant_notes',
        'status',
        'rejection_reason',
        'submitted_at',
        'decided_at',
        'reviewed_by',
    ];

    protected function casts(): array
    {
        return [
            'requested_amount' => 'decimal:2',
            'status' => LoanApplicationStatus::class,
            'submitted_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
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

    public function documents(): HasMany
    {
        return $this->hasMany(LoanApplicationDocument::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(LoanApplicationEvent::class);
    }

    public function loan(): HasOne
    {
        return $this->hasOne(Loan::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}

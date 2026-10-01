<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoanProduct extends Model
{
    protected $fillable = [
        'bank_id',
        'name',
        'slug',
        'description',
        'min_amount',
        'max_amount',
        'min_tenure_months',
        'max_tenure_months',
        'repayment_frequency',
        'annual_rate_percent',
        'processing_fee_percent',
        'processing_fee_fixed',
        'allows_partial_payments',
        'required_documents',
        'eligibility_notes',
        'is_published',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'min_amount' => 'decimal:2',
            'max_amount' => 'decimal:2',
            'annual_rate_percent' => 'decimal:4',
            'processing_fee_percent' => 'decimal:4',
            'processing_fee_fixed' => 'decimal:2',
            'allows_partial_payments' => 'boolean',
            'is_published' => 'boolean',
            'required_documents' => 'array',
            'eligibility_notes' => 'array',
        ];
    }

    public function isAvailable(): bool
    {
        return $this->is_published && $this->status === 'active' && $this->bank?->isActive();
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(LoanApplication::class);
    }
}

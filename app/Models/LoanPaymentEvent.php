<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanPaymentEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'loan_payment_id',
        'provider_event_id',
        'event_type',
        'payload_hash',
        'payload',
        'processed',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (LoanPaymentEvent $event): void {
            $event->created_at ??= now();
        });
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(LoanPayment::class, 'loan_payment_id');
    }
}

<?php

namespace App\Models;

use App\Enums\TransactionSignalSeverity;
use App\Enums\TransactionSignalStatus;
use App\Enums\TransactionSignalType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionSignal extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'transaction_id',
        'comparison_transaction_id',
        'signal_type',
        'severity',
        'status',
        'reason',
        'fingerprint',
        'payload',
        'detected_at',
        'resolved_at',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'signal_type' => TransactionSignalType::class,
            'severity' => TransactionSignalSeverity::class,
            'status' => TransactionSignalStatus::class,
            'payload' => 'array',
            'detected_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Transaction, $this>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * @return BelongsTo<Transaction, $this>
     */
    public function comparisonTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'comparison_transaction_id');
    }
}

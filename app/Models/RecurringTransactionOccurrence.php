<?php

namespace App\Models;

use App\Enums\RecurringOccurrenceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurringTransactionOccurrence extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'recurring_transaction_id',
        'occurrence_date',
        'status',
        'transaction_id',
        'last_reminded_at',
        'decided_at',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'occurrence_date' => 'date',
            'status' => RecurringOccurrenceStatus::class,
            'last_reminded_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<RecurringTransaction, $this>
     */
    public function recurringTransaction(): BelongsTo
    {
        return $this->belongsTo(RecurringTransaction::class);
    }

    /**
     * @return BelongsTo<Transaction, $this>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}

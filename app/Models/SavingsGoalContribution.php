<?php

namespace App\Models;

use Database\Factories\SavingsGoalContributionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavingsGoalContribution extends Model
{
    /** @use HasFactory<SavingsGoalContributionFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'amount',
        'note',
        'contributed_at',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'contributed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<SavingsGoal, $this>
     */
    public function savingsGoal(): BelongsTo
    {
        return $this->belongsTo(SavingsGoal::class);
    }
}

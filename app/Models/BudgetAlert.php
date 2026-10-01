<?php

namespace App\Models;

use App\Enums\BudgetAlertType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetAlert extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'budget_id',
        'alert_type',
        'triggered_at',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'alert_type' => BudgetAlertType::class,
            'triggered_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Budget, $this>
     */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }
}

<?php

namespace App\Models;

use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiCategoryFeedback extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'transaction_type',
        'description',
        'normalized_description',
        'suggested_category_id',
        'selected_category_id',
        'was_accepted',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'transaction_type' => TransactionType::class,
            'was_accepted' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AiCategoryFeedback $feedback): void {
            $feedback->created_at ??= now();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function suggestedCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'suggested_category_id');
    }

    public function selectedCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'selected_category_id');
    }
}

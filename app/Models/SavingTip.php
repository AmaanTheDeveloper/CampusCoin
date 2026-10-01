<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class SavingTip extends Model
{
    protected $fillable = [
        'user_id',
        'tip_key',
        'period',
        'title',
        'body',
        'category_id',
        'impact_level',
        'rank_score',
        'payload',
        'dismissed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'dismissed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function bookmarks(): MorphMany
    {
        return $this->morphMany(Bookmark::class, 'bookmarkable');
    }

    public function isDismissed(): bool
    {
        return $this->dismissed_at !== null;
    }
}

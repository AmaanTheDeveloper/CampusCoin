<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class AiMonthlyInsight extends Model
{
    protected $fillable = [
        'user_id',
        'period',
        'headline',
        'summary',
        'insight_payload',
        'generated_source',
        'status',
        'model',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'insight_payload' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bookmarks(): MorphMany
    {
        return $this->morphMany(Bookmark::class, 'bookmarkable');
    }
}

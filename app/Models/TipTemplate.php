<?php

namespace App\Models;

use App\Enums\TipImpactLevel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TipTemplate extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'created_by',
        'title',
        'body',
        'category_label',
        'impact_level',
        'is_active',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'impact_level' => TipImpactLevel::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

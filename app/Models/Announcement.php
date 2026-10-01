<?php

namespace App\Models;

use App\Enums\AnnouncementPriority;
use App\Enums\AnnouncementStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'created_by',
        'title',
        'message',
        'priority',
        'status',
        'starts_at',
        'ends_at',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'priority' => AnnouncementPriority::class,
            'status' => AnnouncementStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function syncLifecycleStatus(): void
    {
        if ($this->status === AnnouncementStatus::Draft) {
            return;
        }

        $now = now();

        if ($this->ends_at !== null && $this->ends_at->lte($now)) {
            $this->status = AnnouncementStatus::Expired;

            return;
        }

        if ($this->starts_at !== null && $this->starts_at->gt($now)) {
            $this->status = AnnouncementStatus::Scheduled;

            return;
        }

        $this->status = AnnouncementStatus::Active;
    }
}

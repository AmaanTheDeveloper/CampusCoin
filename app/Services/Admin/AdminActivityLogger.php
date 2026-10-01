<?php

namespace App\Services\Admin;

use App\Models\AdminActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AdminActivityLogger
{
    /**
     * @param  array<string, mixed>|null  $meta
     */
    public function log(User $admin, string $action, string $description, ?Model $subject = null, ?array $meta = null): AdminActivityLog
    {
        return AdminActivityLog::query()->create([
            'admin_id' => $admin->id,
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'description' => $description,
            'meta' => $meta,
            'created_at' => now(),
        ]);
    }
}

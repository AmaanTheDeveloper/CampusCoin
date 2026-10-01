<?php

namespace App\Services\Loans;

use App\Models\LoanAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class LoanAuditService
{
    public function log(
        ?User $actor,
        string $action,
        Model $subject,
        ?string $description = null,
        ?int $bankId = null,
        ?array $meta = null,
    ): LoanAuditLog {
        return LoanAuditLog::query()->create([
            'actor_id' => $actor?->id,
            'actor_role' => $actor?->role?->value,
            'action' => $action,
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'bank_id' => $bankId,
            'description' => $description,
            'meta' => $meta,
        ]);
    }
}

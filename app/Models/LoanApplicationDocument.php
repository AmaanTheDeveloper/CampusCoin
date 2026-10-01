<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanApplicationDocument extends Model
{
    protected $fillable = [
        'loan_application_id',
        'document_type',
        'original_name',
        'disk_path',
        'mime_type',
        'size_bytes',
        'status',
        'reviewer_note',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(LoanApplication::class, 'loan_application_id');
    }
}

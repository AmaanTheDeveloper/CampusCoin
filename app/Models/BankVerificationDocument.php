<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankVerificationDocument extends Model
{
    protected $fillable = [
        'bank_id',
        'document_type',
        'original_name',
        'disk_path',
        'mime_type',
        'size_bytes',
        'status',
    ];

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }
}

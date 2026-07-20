<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionLog extends Model
{
    use HasFactory;

    // Tabel ini cuma punya created_at (bukan updated_at), sesuai skema.
    public $timestamps = false;

    protected $fillable = [
        'transaction_id',
        'raw_payload',
        'source',
        'changed_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'raw_payload' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaderboardSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'exam_batch_id',
        'user_id',
        'score',
        'rank',
        'percentile',
        'correct_count',
        'duration_seconds',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'percentile' => 'decimal:2',
            'generated_at' => 'datetime',
        ];
    }

    public function examBatch(): BelongsTo
    {
        return $this->belongsTo(ExamBatch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

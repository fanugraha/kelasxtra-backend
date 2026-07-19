<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaderboardEvent extends Model
{
    protected $fillable = [
        'exam_id',
        'user_id',
        'periode',
        'old_rank',
        'new_rank',
        'is_milestone',
    ];

    protected $casts = [
        'is_milestone' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }
}

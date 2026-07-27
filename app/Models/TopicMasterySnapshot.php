<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TopicMasterySnapshot extends Model
{
    public const TREND_UP = 'up';
    public const TREND_DOWN = 'down';
    public const TREND_STABLE = 'stable';

    protected $fillable = [
        'user_id',
        'topic_id',
        'period',
        'correct_count',
        'total_count',
        'percentage',
        'trend',
        'computed_at',
    ];

    protected function casts(): array
    {
        return [
            'computed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }
}

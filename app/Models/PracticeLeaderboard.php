<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PracticeLeaderboard extends Model
{
    use HasFactory;

    protected $fillable = [
        'exam_id',
        'user_id',
        'periode',
        'skor_terbaik',
        'ranking',
        'reward_type',
        'discount_code',
        'reward_claimed_at',
    ];

    protected function casts(): array
    {
        return [
            'reward_claimed_at' => 'datetime',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

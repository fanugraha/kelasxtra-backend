<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exam extends Model
{
    use HasFactory;

    protected $fillable = [
        'bank_id',
        'title',
        'duration_minutes',
        'passing_score',
        'require_all_sections_pass',
        'is_free_preview',
        'uses_section_timers',
        'is_focus_practice',
    ];

    protected function casts(): array
    {
        return [
            'is_free_preview' => 'boolean',
            'require_all_sections_pass' => 'boolean',
            'uses_section_timers' => 'boolean',
            'is_focus_practice' => 'boolean',
        ];
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(QuestionBank::class, 'bank_id');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(ExamSection::class);
    }

    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'exam_questions')
            ->withPivot('points');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(ExamBatch::class);
    }

    public function practiceLeaderboards(): HasMany
    {
        return $this->hasMany(PracticeLeaderboard::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class);
    }
}

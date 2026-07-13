<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamAttemptSectionScore extends Model
{
    protected $fillable = [
        'exam_attempt_id',
        'exam_section_id',
        'raw_score',
        'correct_count',
        'passed_threshold',
    ];

    protected $casts = [
        'passed_threshold' => 'boolean',
    ];

    public function attempt()
    {
        return $this->belongsTo(ExamAttempt::class, 'exam_attempt_id');
    }

    public function section()
    {
        return $this->belongsTo(ExamSection::class, 'exam_section_id');
    }
}
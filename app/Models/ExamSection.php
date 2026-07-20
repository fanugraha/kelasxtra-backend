<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ExamSection extends Model
{
    protected $fillable = [
        'exam_id',
        'category_id',
        'question_bank_id',
        'code',
        'name',
        'order',
        'scoring_type',
        'min_passing_score',
        'max_score',
        'duration_minutes',
        'is_locked_after_next',
    ];
    protected $casts = [
        'is_locked_after_next' => 'boolean',
    ];
    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }
    public function category()
    {
        return $this->belongsTo(Category::class);
    }
    public function questionBank()
    {
        return $this->belongsTo(QuestionBank::class, 'question_bank_id');
    }
    public function questions()
    {
        return $this->belongsToMany(Question::class, 'exam_questions', 'exam_section_id', 'question_id')
            ->withPivot(['exam_id']);
    }
    public function attemptScores()
    {
        return $this->hasMany(ExamAttemptSectionScore::class);
    }
}

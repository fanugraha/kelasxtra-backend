<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Question extends Model
{
    use HasFactory;
    protected $fillable = [
        'bank_id',
        'question_text',
        'media_url',
        'media_type',
        'passage_id',
        'type',
        'difficulty',
        'explanation',
        'point_correct_override',
        'point_wrong_override',
    ];
    public function bank(): BelongsTo
    {
        return $this->belongsTo(QuestionBank::class, 'bank_id');
    }

    public function pointCorrect(): int
    {
        return $this->point_correct_override ?? $this->bank->point_correct;
    }

    public function pointWrong(): int
    {
        return $this->point_wrong_override ?? $this->bank->point_wrong;
    }
    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class);
    }
    public function passage(): BelongsTo
    {
        return $this->belongsTo(QuestionPassage::class, 'passage_id');
    }
    public function exam(): BelongsToMany
    {
        return $this->belongsToMany(Exam::class, 'exam_questions')
            ->withPivot(['exam_section_id']);
    }
    public function examSections(): BelongsToMany
    {
        return $this->belongsToMany(ExamSection::class, 'exam_questions', 'question_id', 'exam_section_id')
            ->withPivot(['exam_id']);
    }
}

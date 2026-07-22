<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class QuestionBank extends Model
{
    use HasFactory;
    protected $fillable = [
        'subject_id',
        'program_id',
        'category_id',
        'title',
        'scoring_type',
        'point_correct',
        'point_wrong',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $bank) {
            if (blank($bank->program_id) && blank($bank->subject_id)) {
                throw new \InvalidArgumentException('Question bank harus punya program_id atau subject_id.');
            }

            if (filled($bank->program_id)) {
                $program = $bank->program ?? \App\Models\Program::find($bank->program_id);

                if ($program?->usesSubjectMode()) {
                    if (blank($bank->subject_id)) {
                        throw new \InvalidArgumentException('Question bank mode Mapel harus punya subject_id.');
                    }
                } else {
                    if (blank($bank->category_id)) {
                        throw new \InvalidArgumentException('Question bank yang terikat Program (mode Kategori) harus punya category_id.');
                    }
                }
            }
        });
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class, 'bank_id');
    }
    public function examSections(): HasMany
    {
        return $this->hasMany(ExamSection::class, 'question_bank_id');
    }
    public function passages(): HasMany
    {
        return $this->hasMany(QuestionPassage::class);
    }
}

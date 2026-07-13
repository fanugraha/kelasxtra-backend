<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
class QuestionBank extends Model
{
    use HasFactory;
    protected $fillable = [
        'subject_id',
        'program_id',
        'title',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $bank) {
            if (blank($bank->program_id) && blank($bank->subject_id)) {
                throw new \InvalidArgumentException('Question bank harus punya program_id atau subject_id.');
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
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class, 'bank_id');
    }
    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class, 'bank_id');
    }
    public function sections(): HasMany
    {
        return $this->hasMany(QuestionBankSection::class);
    }
    public function passages(): HasMany
    {
        return $this->hasMany(QuestionPassage::class);
    }

    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(Package::class, 'package_question_bank');
    }
}
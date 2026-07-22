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
        'taxonomy_id',
        'program_id',
        'title',
        'scoring_type',
        'point_correct',
        'point_wrong',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $bank) {
            if (blank($bank->program_id) && blank($bank->taxonomy_id)) {
                throw new \InvalidArgumentException('Question bank harus punya program_id atau taxonomy_id.');
            }

            if (filled($bank->program_id) && blank($bank->taxonomy_id)) {
                throw new \InvalidArgumentException('Question bank yang terikat Program harus punya taxonomy_id.');
            }
        });
    }

    public function taxonomy(): BelongsTo
    {
        return $this->belongsTo(Taxonomy::class);
    }
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
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

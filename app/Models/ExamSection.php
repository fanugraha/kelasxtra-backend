<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
class ExamSection extends Model
{
    protected $fillable = [
        'exam_id',
        'taxonomy_id',
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
    public function taxonomy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Taxonomy::class);
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

    public function getTaxonomyNameAttribute(): ?string
    {
        return $this->taxonomy?->name;
    }

    /**
     * Unifikasi P1.5: scoring_type dulu disimpan dobel (question_banks DAN
     * exam_sections), dijaga sinkron lewat write-side hook di
     * QuestionBank::booted(). Sekarang section dengan question_bank_id yang
     * terisi TIDAK PERNAH baca kolomnya sendiri lagi -- selalu ambil live
     * dari bank sumbernya, jadi tidak ada lagi kemungkinan dua nilai beda
     * (tidak perlu ditulis-ulang, tidak bisa basi).
     *
     * Kolom exam_sections.scoring_type masih dipertahankan (NOT NULL di
     * skema) karena section Latihan Topik (question_bank_id NULL, lihat
     * TopicPartGenerator) tidak punya bank sumber sama sekali -- untuk
     * section itu, kolom sendiri di sini TETAP jadi satu-satunya sumber
     * kebenaran.
     */
    protected function scoringType(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $this->question_bank_id
                ? ($this->questionBank?->scoring_type ?? $value)
                : $value,
        );
    }
}

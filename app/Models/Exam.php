<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\ExamAttemptSectionScore;

class Exam extends Model
{
    use HasFactory;

    protected $fillable = [
        'program_id',
        'title',
        'duration_minutes',
        'passing_score',
        'require_all_sections_pass',
        'is_free_preview',
        'uses_section_timers',
        'focus_mode',
        'focus_taxonomy_id',
    ];

    protected function casts(): array
    {
        return [
            'is_free_preview' => 'boolean',
            'require_all_sections_pass' => 'boolean',
            'uses_section_timers' => 'boolean',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function focusTaxonomy(): BelongsTo
    {
        return $this->belongsTo(Taxonomy::class, 'focus_taxonomy_id');
    }

    public function isFocusTopic(): bool
    {
        return $this->focus_mode === 'focus_topic';
    }

    public function sections(): HasMany
    {
        return $this->hasMany(ExamSection::class);
    }

    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'exam_questions')
            ->withPivot(['exam_section_id']);
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

    /**
     * Satu-satunya jalur resmi untuk mengisi Exam dari Bank Soal.
     * Auto-membuat/reuse ExamSection sesuai kategori bank, lalu
     * menautkan semua soal di bank itu ke section tersebut.
     */
    public function attachBank(QuestionBank $bank, array $sectionAttributes = []): ExamSection
    {
        if ($bank->program_id !== $this->program_id) {
            throw new \InvalidArgumentException('Bank Soal harus berasal dari Program yang sama dengan Exam ini.');
        }

        if (blank($bank->taxonomy_id)) {
            throw new \InvalidArgumentException('Bank Soal ini belum punya Taxonomy.');
        }

        $taxonomy = $bank->taxonomy;
        $taxonomyId = $bank->taxonomy_id;
        $taxonomyName = $taxonomy->name ?? $bank->title;

        $existing = $this->sections()->where('taxonomy_id', $taxonomyId)->first();

        if ($existing && $existing->question_bank_id !== $bank->id) {
            throw new \InvalidArgumentException('Taxonomy ini sudah diisi oleh Bank Soal lain di Exam ini.');
        }

        $section = $existing ?? $this->sections()->create(array_merge([
            'taxonomy_id' => $taxonomyId,
            'question_bank_id' => $bank->id,
            'code' => $taxonomy->code ?? strtoupper(substr($taxonomyName, 0, 3)),
            'name' => $taxonomyName,
            'scoring_type' => $bank->scoring_type,
        ], $sectionAttributes));

        return $this->syncSectionQuestions($section, $bank);
    }

    protected function syncSectionQuestions(ExamSection $section, QuestionBank $bank): ExamSection
    {
        foreach ($bank->questions()->pluck('id') as $questionId) {
            $this->questions()->syncWithoutDetaching([
                $questionId => ['exam_section_id' => $section->id],
            ]);
        }

        return $section;
    }

    /**
     * Kebalikan dari attachBank(): melepas semua soal dari section ini
     * (hapus baris pivot exam_questions), lalu hapus section-nya sendiri.
     * Section tanpa soal tidak berguna, jadi sekalian dibersihkan supaya
     * admin tidak perlu 2 langkah manual (detach lalu hapus section).
     */
    public function detachSection(ExamSection $section): void
    {
        if (ExamAttemptSectionScore::where('exam_section_id', $section->id)->exists()) {
            throw new \Exception('Bagian ujian ini tidak bisa dihapus karena sudah ada siswa yang mengerjakan dan memiliki nilai tersimpan.');
        }

        $this->questions()->wherePivot('exam_section_id', $section->id)->detach();

        $section->delete();
    }

    /**
     * Satu-satunya jalur resmi untuk menentukan status lulus/tidak sebuah
     * attempt. Sebelumnya logic ini diduplikasi di 3 tempat (ExamController
     * x2, ExamAttemptResource) dengan celah berbeda-beda -- disatukan di sini
     * supaya semua endpoint konsisten kalau aturan kelulusan berubah nanti.
     *
     * null  = exam ini tidak punya aturan kelulusan sama sekali.
     * true  = lulus.
     * false = tidak lulus.
     */
    public function isAttemptPassed(ExamAttempt $attempt): ?bool
    {
        if ($this->require_all_sections_pass) {
            $sections = $this->sections;

            if ($sections->isEmpty()) {
                return null;
            }

            return $sections->every(function (ExamSection $section) use ($attempt) {
                // Section tanpa syarat lulus sendiri dianggap otomatis lolos,
                // bukan bikin keseluruhan attempt gagal terus-terusan.
                if ($section->min_passing_score === null) {
                    return true;
                }

                $result = $attempt->sectionScores->firstWhere('exam_section_id', $section->id);

                return $result?->passed_threshold === true;
            });
        }

        if ($this->passing_score !== null) {
            return $attempt->score >= $this->passing_score;
        }

        return null;
    }
}

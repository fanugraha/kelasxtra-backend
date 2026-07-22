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
        'program_id',
        'title',
        'duration_minutes',
        'passing_score',
        'require_all_sections_pass',
        'is_free_preview',
        'uses_section_timers',
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

    public function sections(): HasMany
    {
        return $this->hasMany(ExamSection::class);
    }

    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'exam_questions');
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

        // Satu jalur tunggal, ditentukan dari mode Program (bukan lagi
        // dari cek kolom mana yang terisi di Bank Soal). taxonomy_id diisi
        // di sini supaya query section ke depannya tidak perlu tahu lagi
        // apakah dia category atau subject -- cukup baca taxonomy_id +
        // program->usesSubjectMode() untuk interpretasinya.
        $usesSubjectMode = $this->program->usesSubjectMode();

        if ($usesSubjectMode && blank($bank->subject_id)) {
            throw new \InvalidArgumentException('Program ini pakai mode Mapel, tapi Bank Soal ini belum punya Mapel.');
        }

        if (! $usesSubjectMode && blank($bank->category_id)) {
            throw new \InvalidArgumentException('Program ini pakai mode Kategori, tapi Bank Soal ini belum punya Kategori.');
        }

        $taxonomy = $this->resolveTaxonomy($usesSubjectMode, $bank);
        $taxonomyId = $taxonomy->id;
        $taxonomyName = $taxonomy->name ?? $bank->title;

        $existing = $this->sections()->where('taxonomy_id', $taxonomyId)->first();

        if ($existing && $existing->question_bank_id !== $bank->id) {
            $label = $usesSubjectMode ? 'Mapel' : 'Kategori';
            throw new \InvalidArgumentException("{$label} ini sudah diisi oleh Bank Soal lain di Exam ini.");
        }

        $section = $existing ?? $this->sections()->create(array_merge([
            'taxonomy_id' => $taxonomyId,
            'question_bank_id' => $bank->id,
            'code' => $usesSubjectMode
                ? strtoupper(substr($taxonomyName, 0, 3))
                : ($bank->category->code ?? strtoupper(substr($taxonomyName, 0, 3))),
            'name' => $taxonomyName,
            'scoring_type' => $bank->scoring_type,
        ], $sectionAttributes));

        return $this->syncSectionQuestions($section, $bank);
    }

    /**
     * Menerjemahkan subject_id/category_id LAMA di Bank Soal ke id
     * baru di tabel taxonomies (hasil unifikasi categories+subjects).
     */
    protected function resolveTaxonomy(bool $usesSubjectMode, QuestionBank $bank): Taxonomy
    {
        $taxonomy = $usesSubjectMode
            ? Taxonomy::subjects()
                ->where('legacy_subject_id', $bank->subject_id)
                ->first()
            : Taxonomy::categories()
                ->where('program_id', $this->program_id)
                ->where('legacy_category_id', $bank->category_id)
                ->first();

        if (! $taxonomy) {
            throw new \RuntimeException(
                "Taxonomy tidak ditemukan untuk bank #{$bank->id} (mode: " .
                ($usesSubjectMode ? 'subject' : 'category') . ")."
            );
        }

        return $taxonomy;
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
        $this->questions()->wherePivot('exam_section_id', $section->id)->detach();

        $section->delete();
    }
}

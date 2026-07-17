<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'exam_id',
        'exam_batch_id',
        'bank_id',
        'score',
        'correct_count',
        'started_at',
        'finished_at',
        'status',
        'question_order',
        'tab_switch_count',
        'current_section_id',
        'section_started_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'section_started_at' => 'datetime',
            // Urutan soal & opsi hasil randomisasi, disimpan sebagai array supaya
            // konsisten kalau siswa reload halaman (lihat AGENTS: exam engine).
            'question_order' => 'array',
        ];
    }

    /**
     * Hitung ulang skor total attempt secara aman (Mendukung TKP & Essay)
     */
    public function recalculateScore(): void
    {
        // Eager load answers beserta question dan options untuk efisiensi database
        $answers = $this->answers()->with('question.options')->get();
        $examQuestions = $this->exam->questions;

        $score = 0;
        $correctCount = 0;

        foreach ($answers as $ans) {
            if ($ans->question->type === 'pg') {
                // Ambil poin langsung dari opsi pilihan siswa (Mendukung TKP & PG Biasa)
                $selectedOption = $ans->question->options->firstWhere('id', $ans->selected_option_id);
                $points = $selectedOption->points ?? 0;
                $score += $points;

                if ($selectedOption && $selectedOption->is_correct) {
                    $correctCount++;
                }
            } elseif ($ans->question->type === 'essay') {
                // Soal essay dinilai berdasarkan bobot di pivot exam_questions jika benar
                if ($ans->is_correct) {
                    $points = $examQuestions->firstWhere('id', $ans->question_id)?->pivot->points ?? 0;
                    $score += $points;
                    $correctCount++;
                }
            }
        }

        // Cek apakah masih ada esai yang belum dinilai oleh tutor
        $hasPendingEssay = $answers->where('question.type', 'essay')->whereNull('is_correct')->isNotEmpty();

        $this->update([
            'score' => $score,
            'correct_count' => $correctCount,
            'status' => $hasPendingEssay ? 'submitted' : 'graded',
        ]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function examBatch(): BelongsTo
    {
        return $this->belongsTo(ExamBatch::class);
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(QuestionBank::class, 'bank_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ExamAnswer::class, 'attempt_id');
    }

    public function sectionScores(): HasMany
    {
        return $this->hasMany(ExamAttemptSectionScore::class);
    }

    public function currentSection(): BelongsTo
    {
        return $this->belongsTo(ExamSection::class, 'current_section_id');
    }
}

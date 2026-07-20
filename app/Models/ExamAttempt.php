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
     * Hitung ulang skor total attempt setelah tutor menilai essay (lihat
     * TutorGradingController::grade()). Sekarang delegasi ke ExamScoringService
     * -- rumus yang SAMA PERSIS dengan yang dipakai saat siswa submit
     * (ExamController::gradeAndClose()). Sebelumnya fungsi ini punya rumus
     * sendiri yang salah untuk soal single_correct (TWK/TIU): lihat
     * ExamScoringService untuk penjelasan bug-nya.
     */
    public function recalculateScore(): void
    {
        $result = app(\App\Services\ExamScoringService::class)->scoreAndPersist($this);

        $this->update([
            'score' => $result['score'],
            'correct_count' => $result['correct_count'],
            // Kalau masih ada essay pending, pertahankan status attempt yang
            // lama (submitted/auto_submitted) -- jangan dipaksa 'submitted'
            // seperti versi sebelumnya, supaya asal status tidak hilang.
            'status' => $result['has_pending_essay'] ? $this->status : 'graded',
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

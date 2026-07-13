<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExamAttemptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $deadline = $this->started_at?->clone()->addMinutes($this->exam->duration_minutes);
        $remainingSeconds = $deadline ? max(0, now()->diffInSeconds($deadline, false)) : null;

        return [
            'id' => $this->id,
            'exam_id' => $this->exam_id,
            'exam_batch_id' => $this->exam_batch_id,
            'status' => $this->status,
            'started_at' => $this->started_at,
            'finished_at' => $this->finished_at,
            'duration_minutes' => $this->exam->duration_minutes,
            'passing_score' => $this->exam->passing_score,
            'remaining_seconds' => $this->status === 'in_progress' ? $remainingSeconds : 0,
            'tab_switch_count' => $this->tab_switch_count,
            'question_order' => $this->when(
                $this->status === 'in_progress',
                $this->question_order
            ),
            // Detail teks soal & opsi (tanpa is_correct, biar tidak bocor ke siswa).
            // Frontend menyusun urutan tampil pakai question_order di atas.
            // category: null untuk soal lama yang belum dikategorikan (mis. testing awal).
            'questions' => $this->when(
                $this->status === 'in_progress',
                fn () => $this->exam->questions->map(fn ($q) => [
                    'id' => $q->id,
                    'question_text' => $q->question_text,
                    'image_url' => $q->image_url,
                    'type' => $q->type,
                    'category' => $q->category,
                    'options' => $q->type === 'pg'
                        ? $q->options->map(fn ($o) => [
                            'id' => $o->id,
                            'option_text' => $o->option_text,
                        ])
                        : [],
                ])
            ),
            // BARU: jawaban yang sudah tersimpan, supaya tidak hilang saat attempt di-reload.
            // Format eksplisit question_id (bukan key array/object) — hindari masalah
            // ambiguitas JSON encode seperti yang terjadi di question_order sebelumnya.
            'answers' => $this->when(
                $this->status === 'in_progress',
                fn () => $this->answers->map(fn ($a) => [
                    'question_id' => $a->question_id,
                    'selected_option_id' => $a->selected_option_id,
                    'essay_answer' => $a->essay_answer,
                ])->values()
            ),
            'score' => $this->when($this->status !== 'in_progress', $this->score),
            'correct_count' => $this->when($this->status !== 'in_progress', $this->correct_count),
            // BARU: breakdown skor per section (TWK/TIU/TKP, dst) — cuma muncul
            // setelah attempt selesai (bukan in_progress), sama seperti score/correct_count.
            // Kalau exam ini belum punya section terdaftar (mis. exam lama sebelum
            // migrasi skema fleksibel), $this->exam->sections akan collection kosong
            // dan field ini otomatis jadi array kosong -- aman, tidak error.
            'sections' => $this->when(
                $this->status !== 'in_progress',
                fn () => $this->exam->sections->map(function ($section) {
                    $result = $this->sectionScores->firstWhere('exam_section_id', $section->id);

                    return [
                        'code' => $section->code,
                        'name' => $section->name,
                        'raw_score' => $result?->raw_score ?? 0,
                        'correct_count' => $result?->correct_count ?? 0,
                        'max_score' => $section->max_score,
                        'min_passing_score' => $section->min_passing_score,
                        'passed_threshold' => $result?->passed_threshold,
                    ];
                })
            ),
            'has_pending_essay' => $this->when(
                in_array($this->status, ['submitted', 'auto_submitted']),
                fn () => $this->answers()->where('needs_manual_grading', true)->exists()
            ),
        ];
    }
}
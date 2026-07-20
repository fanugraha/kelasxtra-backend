<?php

namespace App\Services;

use App\Models\ExamAttempt;
use App\Models\ExamAttemptSectionScore;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya sumber logic penghitungan skor exam attempt -- dipakai oleh
 * ExamController::gradeAndClose() (submit/auto-submit siswa) DAN
 * ExamAttempt::recalculateScore() (dipanggil ulang setelah tutor menilai
 * essay, lihat TutorGradingController::grade()).
 *
 * SEBELUM diekstrak ke sini, dua tempat itu punya rumus BEDA:
 * recalculateScore() versi lama selalu ambil poin dari
 * question_options.points untuk semua soal pg. Itu benar untuk section
 * weighted_options (TKP), tapi SALAH untuk section single_correct (TWK/TIU)
 * -- soal jenis itu poinnya disimpan di pivot exam_questions.points, bukan
 * di opsi. Akibatnya: begitu tutor menilai 1 soal essay pada attempt yang
 * JUGA berisi jawaban TWK/TIU, recalculateScore() ke-trigger dan menghitung
 * ulang skor TWK/TIU pakai poin opsi (biasanya kosong/0) -- skor TWK/TIU
 * siswa hilang/berubah jadi 0 secara diam-diam.
 */
class ExamScoringService
{
    /**
     * Hitung ulang skor attempt, tulis is_correct per jawaban dan baris
     * exam_attempt_section_scores, lalu kembalikan ringkasannya. Caller
     * bertanggung jawab menyimpan score/correct_count/status ke $attempt
     * itu sendiri (beda antara gradeAndClose yang juga set finished_at,
     * vs recalculateScore yang tidak).
     *
     * @return array{score: int, correct_count: int, has_pending_essay: bool}
     */
    public function scoreAndPersist(ExamAttempt $attempt): array
    {
        return DB::transaction(function () use ($attempt) {
            $answers = $attempt->answers()->with('question.options')->get();
            $examQuestions = $attempt->exam->questions; // pivot: points, exam_section_id
            $sections = $attempt->exam->sections->keyBy('id');

            $score = 0;
            $correctCount = 0;
            $hasPendingEssay = false;
            $sectionTotals = [];

            foreach ($answers as $answer) {
                $pivot = $examQuestions->firstWhere('id', $answer->question_id)?->pivot;
                $sectionId = $pivot?->exam_section_id;

                if ($answer->question->type === 'essay') {
                    if ($answer->needs_manual_grading) {
                        $hasPendingEssay = true;
                        continue;
                    }

                    if ($answer->is_correct) {
                        $points = $pivot->points ?? 0;
                        $score += $points;
                        $correctCount++;

                        if ($sectionId) {
                            $sectionTotals[$sectionId]['score'] = ($sectionTotals[$sectionId]['score'] ?? 0) + $points;
                            $sectionTotals[$sectionId]['correct'] = ($sectionTotals[$sectionId]['correct'] ?? 0) + 1;
                        }
                    }
                    continue;
                }

                // soal pg: cabang berbeda tergantung scoring_type section.
                // - weighted_options (TKP): tiap opsi punya points sendiri (1-5),
                //   tidak ada "salah" -- selectedOption->points dipakai langsung.
                // - single_correct (TWK/TIU, default): opsi benar dicek via
                //   is_correct, poin diambil dari bobot soal di pivot
                //   exam_questions (BUKAN dari opsi), fallback 1 poin kalau
                //   bobot belum diisi.
                $selectedOption = $answer->question->options->firstWhere('id', $answer->selected_option_id);
                $scoringType = $sections->get($sectionId)?->scoring_type;

                if ($scoringType === 'weighted_options') {
                    $points = $selectedOption->points ?? 0;
                    $isCorrect = $points > 0;
                } else {
                    $isCorrect = (bool) ($selectedOption->is_correct ?? false);
                    $points = $isCorrect ? ($pivot->points ?? 1) : 0;
                }

                $answer->update(['is_correct' => $isCorrect]);

                $score += $points;
                if ($isCorrect) {
                    $correctCount++;
                }

                if ($sectionId) {
                    $sectionTotals[$sectionId]['score'] = ($sectionTotals[$sectionId]['score'] ?? 0) + $points;
                    $sectionTotals[$sectionId]['correct'] = ($sectionTotals[$sectionId]['correct'] ?? 0) + ($isCorrect ? 1 : 0);
                }
            }

            foreach ($sectionTotals as $sectionId => $totals) {
                $section = $sections->get($sectionId);
                $passed = $section?->min_passing_score !== null
                    ? $totals['score'] >= $section->min_passing_score
                    : null;

                ExamAttemptSectionScore::updateOrCreate(
                    ['exam_attempt_id' => $attempt->id, 'exam_section_id' => $sectionId],
                    [
                        'raw_score' => $totals['score'],
                        'correct_count' => $totals['correct'],
                        'passed_threshold' => $passed,
                    ]
                );
            }

            return [
                'score' => $score,
                'correct_count' => $correctCount,
                'has_pending_essay' => $hasPendingEssay,
            ];
        });
    }
}

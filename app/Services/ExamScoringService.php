<?php

namespace App\Services;

use App\Models\ExamAttempt;
use App\Models\ExamAttemptSectionScore;
use App\Models\ExamAttemptTopicScore;
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
 * -- soal jenis itu poinnya (saat itu) disimpan di pivot
 * exam_questions.points, bukan di opsi. Akibatnya: begitu tutor menilai 1
 * soal essay pada attempt yang JUGA berisi jawaban TWK/TIU,
 * recalculateScore() ke-trigger dan menghitung ulang skor TWK/TIU pakai
 * poin opsi (biasanya kosong/0) -- skor TWK/TIU siswa hilang/berubah jadi 0
 * secara diam-diam.
 *
 * CATATAN: kolom exam_questions.points yang disebut di atas sudah DIHAPUS
 * dari skema (lihat migration drop_points_from_exam_questions_table).
 * Sumber poin single_correct sekarang murni dari Question::pointCorrect().
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
            $examQuestions = $attempt->exam->questions; // pivot: exam_section_id
            // eager-load questionBank: ExamSection::scoring_type (P1.5) baca
            // live dari bank sumbernya begitu question_bank_id terisi -- tanpa
            // ini, tiap section attached-bank akan lazy-load 1 query terpisah
            // di baris scoring_type di bawah.
            $sections = $attempt->exam->sections()->with('questionBank')->get()->keyBy('id');

            $score = 0;
            $correctCount = 0;
            $hasPendingEssay = false;
            $sectionTotals = [];
            $topicTotals = [];

            foreach ($answers as $answer) {
                $pivot = $examQuestions->firstWhere('id', $answer->question_id)?->pivot;
                $sectionId = $pivot?->exam_section_id;
                $topicId = $answer->question->topic_id;

                if ($answer->question->type === 'essay') {
                    if ($answer->needs_manual_grading) {
                        $hasPendingEssay = true;
                        continue;
                    }

                    if ($topicId) {
                        $topicTotals[$topicId]['total'] = ($topicTotals[$topicId]['total'] ?? 0) + 1;
                    }

                    if ($answer->is_correct) {
                        $points = $answer->question->pointCorrect();
                        $score += $points;
                        $correctCount++;

                        if ($sectionId) {
                            $sectionTotals[$sectionId]['score'] = ($sectionTotals[$sectionId]['score'] ?? 0) + $points;
                            $sectionTotals[$sectionId]['correct'] = ($sectionTotals[$sectionId]['correct'] ?? 0) + 1;
                        }

                        if ($topicId) {
                            $topicTotals[$topicId]['correct'] = ($topicTotals[$topicId]['correct'] ?? 0) + 1;
                        }
                    }
                    continue;
                }

                // soal pg: cabang berbeda tergantung scoring_type section.
                // - weighted_options (TKP): tiap opsi punya points sendiri (1-5),
                //   tidak ada "salah" -- selectedOption->points dipakai langsung.
                // - single_correct (TWK/TIU, default): opsi benar dicek via
                //   is_correct, poin diambil dari Question::pointCorrect()
                //   (override per-soal, fallback ke default point_correct bank
                //   soal). Kolom exam_questions.points versi lama sudah dihapus
                //   dari skema -- pivot exam_questions sekarang cuma menyimpan
                //   exam_section_id (lihat $examQuestions di bawah).
                $selectedOption = $answer->question->options->firstWhere('id', $answer->selected_option_id);
                $scoringType = $sections->get($sectionId)?->scoring_type;

                if ($scoringType === 'weighted_options') {
                    $points = $selectedOption->points ?? 0;
                    $isCorrect = $points > 0;
                } else {
                    $isCorrect = (bool) ($selectedOption->is_correct ?? false);
                    $points = $isCorrect ? $answer->question->pointCorrect() : 0;
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

                if ($topicId) {
                    $topicTotals[$topicId]['total'] = ($topicTotals[$topicId]['total'] ?? 0) + 1;
                    $topicTotals[$topicId]['correct'] = ($topicTotals[$topicId]['correct'] ?? 0) + ($isCorrect ? 1 : 0);
                }
            }

            foreach ($sectionTotals as $sectionId => $totals) {
                $section = $sections->get($sectionId);

                // Cap skor section ke max_score kalau diisi, supaya field itu
                // beneran ditegakkan sistem, bukan cuma display. Log kalau
                // terjadi capping, biar ketahuan ada mismatch poin soal vs
                // max_score yang diset di section.
                $sectionScore = $totals['score'];
                if ($section?->max_score !== null && $sectionScore > $section->max_score) {
                    \Log::warning('Section score exceeds max_score, capping applied', [
                        'exam_attempt_id' => $attempt->id,
                        'exam_section_id' => $sectionId,
                        'raw_score' => $sectionScore,
                        'max_score' => $section->max_score,
                    ]);
                    $sectionScore = $section->max_score;
                }

                $passed = $section?->min_passing_score !== null
                    ? $sectionScore >= $section->min_passing_score
                    : null;

                ExamAttemptSectionScore::updateOrCreate(
                    ['exam_attempt_id' => $attempt->id, 'exam_section_id' => $sectionId],
                    [
                        'raw_score' => $sectionScore,
                        'correct_count' => $totals['correct'],
                        'passed_threshold' => $passed,
                    ]
                );
            }

            foreach ($topicTotals as $topicId => $totals) {
                ExamAttemptTopicScore::updateOrCreate(
                    ['exam_attempt_id' => $attempt->id, 'topic_id' => $topicId],
                    [
                        'correct_count' => $totals['correct'] ?? 0,
                        'total_count' => $totals['total'] ?? 0,
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

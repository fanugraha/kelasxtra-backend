<?php

namespace Database\Seeders;

use App\Models\ExamAttempt;
use App\Models\ExamAttemptSectionScore;
use App\Models\ExamAttemptTopicScore;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DemoPerformanceSeeder extends Seeder
{
    public function run(): void
    {
        $userId = 1; // admin@gmail.com

        // [exam_id, section_id, finished_at, topics]
        // topics: topic_id => [total_soal, jumlah_benar]
        $attempts = [
            [1, 1, '2026-07-23 18:00:00', [
                6 => [20, 8],  // Pilar Negara      -> weak (40%)
                3 => [12, 6],  // Nasionalisme      -> weak (50%)
                7 => [2, 1],   // Bahasa Indonesia  -> insufficient_data
            ]],
            [2, 2, '2026-07-23 19:00:00', [
                5 => [15, 10], // Bela Negara       -> medium (67%)
                4 => [3, 2],   // Integritas        -> insufficient_data
            ]],
            [6, 6, '2026-07-23 20:00:00', [
                9  => [18, 15], // Analitis                 -> strong (83%)
                8  => [16, 14], // Analogi                  -> strong (88%)
                10 => [10, 6],  // Silogisme                -> medium (60%)
            ]],
            [7, 8, '2026-07-23 21:00:00', [
                11 => [14, 9], // Deret Angka               -> medium (64%)
                12 => [4, 2],  // Berhitung                 -> insufficient_data
                13 => [12, 5], // Perbandingan Kuantitatif  -> weak (42%)
            ]],
            [6, 6, '2026-07-23 22:00:00', [
                14 => [10, 8], // Soal Cerita               -> strong (80%)
                15 => [8, 6],  // Analogi Gambar            -> medium (75%)
                16 => [3, 1],  // Ketidaksamaan Gambar      -> insufficient_data
                17 => [6, 5],  // Serial Gambar             -> strong (83%)
            ]],
        ];

        foreach ($attempts as [$examId, $sectionId, $finishedAt, $topics]) {
            $sectionTotal = array_sum(array_column($topics, 0));
            $sectionCorrect = array_sum(array_column($topics, 1));
            $sectionScorePct = (int) round(($sectionCorrect / $sectionTotal) * 100);

            $finishedAtCarbon = Carbon::parse($finishedAt);

            $attempt = ExamAttempt::create([
                'user_id' => $userId,
                'exam_id' => $examId,
                'score' => $sectionScorePct,
                'correct_count' => $sectionCorrect,
                'started_at' => (clone $finishedAtCarbon)->subMinutes(45),
                'finished_at' => $finishedAtCarbon,
                'status' => 'graded',
                'tab_switch_count' => 0,
            ]);

            ExamAttemptSectionScore::create([
                'exam_attempt_id' => $attempt->id,
                'exam_section_id' => $sectionId,
                'raw_score' => $sectionScorePct,
                'correct_count' => $sectionCorrect,
                'passed_threshold' => $sectionScorePct >= 60,
            ]);

            foreach ($topics as $topicId => [$total, $correct]) {
                ExamAttemptTopicScore::create([
                    'exam_attempt_id' => $attempt->id,
                    'topic_id' => $topicId,
                    'correct_count' => $correct,
                    'total_count' => $total,
                    'raw_score' => (int) round(($correct / $total) * 100),
                ]);
            }
        }
    }
}

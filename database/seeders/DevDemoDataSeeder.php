<?php

namespace Database\Seeders;

use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptSectionScore;
use App\Models\ExamAttemptTopicScore;
use App\Models\ExamBatch;
use App\Models\LeaderboardSnapshot;
use App\Models\Package;
use App\Models\Program;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\QuestionOption;
use App\Models\Taxonomy;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DevDemoDataSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $this->seed();
        });
    }

    protected function seed(): void
    {
        // 1. Admin user (email terverifikasi)
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@gmail.com',
            'password' => bcrypt('admin123'),
            'role' => 'admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        // 2. Program
        $program = Program::create([
            'name' => 'CPNS 2026',
            'slug' => 'cpns-2026',
            'description' => 'Persiapan Seleksi Kompetensi Dasar CPNS 2026',
            'is_active' => true,
            'question_grouping_mode' => 'category',
        ]);

        // 3. Taxonomies (kategori SKD)
        $twk = Taxonomy::create(['program_id' => $program->id, 'type' => 'category', 'code' => 'TWK', 'name' => 'Tes Wawasan Kebangsaan', 'passing_grade' => 65]);
        $tiu = Taxonomy::create(['program_id' => $program->id, 'type' => 'category', 'code' => 'TIU', 'name' => 'Tes Intelegensia Umum', 'passing_grade' => 80]);
        $tkp = Taxonomy::create(['program_id' => $program->id, 'type' => 'category', 'code' => 'TKP', 'name' => 'Tes Karakteristik Pribadi', 'passing_grade' => 166]);

        // 4. Topik per kategori (kisi-kisi Kemenpan-RB)
        $twkTopicNames = ['Nasionalisme', 'Integritas', 'Bela Negara', 'Pilar Negara', 'Bahasa Indonesia'];
        $tiuTopicNames = ['Kemampuan Verbal', 'Kemampuan Numerik', 'Kemampuan Figural'];
        $tkpTopicNames = ['Pelayanan Publik', 'Jejaring Kerja', 'Sosial Budaya', 'TIK', 'Profesionalisme', 'Anti Radikalisme'];

        $twkTopics = collect($twkTopicNames)->mapWithKeys(fn ($n, $i) => [
            $n => Topic::create(['taxonomy_id' => $twk->id, 'code' => 'TWK' . ($i + 1), 'name' => $n]),
        ]);
        $tiuTopics = collect($tiuTopicNames)->mapWithKeys(fn ($n, $i) => [
            $n => Topic::create(['taxonomy_id' => $tiu->id, 'code' => 'TIU' . ($i + 1), 'name' => $n]),
        ]);
        $tkpTopics = collect($tkpTopicNames)->mapWithKeys(fn ($n, $i) => [
            $n => Topic::create(['taxonomy_id' => $tkp->id, 'code' => 'TKP' . ($i + 1), 'name' => $n]),
        ]);

        // 5. Question banks
        $twkBank = QuestionBank::create(['taxonomy_id' => $twk->id, 'program_id' => $program->id, 'title' => 'Bank Soal TWK', 'scoring_type' => 'single_correct', 'point_correct' => 5, 'point_wrong' => 0]);
        $tiuBank = QuestionBank::create(['taxonomy_id' => $tiu->id, 'program_id' => $program->id, 'title' => 'Bank Soal TIU', 'scoring_type' => 'single_correct', 'point_correct' => 5, 'point_wrong' => 0]);
        $tkpBank = QuestionBank::create(['taxonomy_id' => $tkp->id, 'program_id' => $program->id, 'title' => 'Bank Soal TKP', 'scoring_type' => 'weighted_options', 'point_correct' => 5, 'point_wrong' => 0]);

        // 6. Soal per topik (jumlah sesuai proporsi SKD asli: TWK 30, TIU 35, TKP 45)
        $twkCounts = [6, 6, 6, 6, 6];
        $tiuCounts = [12, 12, 11];
        $tkpCounts = [8, 8, 8, 7, 7, 7];

        $twkQuestions = $this->createQuestionsPerTopic($twkBank, $twkTopics, $twkCounts, 'single_correct');
        $tiuQuestions = $this->createQuestionsPerTopic($tiuBank, $tiuTopics, $tiuCounts, 'single_correct');
        $tkpQuestions = $this->createQuestionsPerTopic($tkpBank, $tkpTopics, $tkpCounts, 'weighted');

        // 7. Exam + 3 section (lewat attachBank supaya konsisten dengan alur resmi)
        $exam = Exam::create([
            'program_id' => $program->id,
            'title' => 'Try Out SKD CPNS 2026 - Paket 1',
            'duration_minutes' => 100,
            'require_all_sections_pass' => true,
            'is_free_preview' => false,
            'uses_section_timers' => false,
            'focus_mode' => 'full',
        ]);

        $twkSection = $exam->attachBank($twkBank, ['order' => 1, 'min_passing_score' => 65, 'max_score' => 150]);
        $tiuSection = $exam->attachBank($tiuBank, ['order' => 2, 'min_passing_score' => 80, 'max_score' => 175]);
        $tkpSection = $exam->attachBank($tkpBank, ['order' => 3, 'min_passing_score' => 166, 'max_score' => 225]);

        // 8. Package + attach exam
        $package = Package::create([
            'program_id' => $program->id,
            'name' => 'Paket Try Out SKD CPNS 2026',
            'type' => 'latihan_soal',
            'price' => 150000,
            'duration_days' => 90,
            'description' => 'Akses try out & pembahasan lengkap SKD CPNS 2026',
        ]);
        $package->exams()->attach($exam->id);

        // 9. Enrollment aktif untuk admin
        Enrollment::create([
            'user_id' => $admin->id,
            'package_id' => $package->id,
            'status' => 'active',
            'start_date' => now()->subDays(10),
            'end_date' => now()->addDays(80),
        ]);

        // 10. Persentase kebenaran per topik -- dibuat bervariasi (lemah/sedang/kuat)
        // supaya fitur analisis kekuatan & kelemahan benar-benar teruji.
        // attempt1 = kemarin, attempt2 = hari ini (dengan tren naik/turun berbeda per topik).
        $pct1 = [
            'Nasionalisme' => 75, 'Integritas' => 55, 'Bela Negara' => 65, 'Pilar Negara' => 80, 'Bahasa Indonesia' => 70,
            'Kemampuan Verbal' => 55, 'Kemampuan Numerik' => 60, 'Kemampuan Figural' => 80,
            'Pelayanan Publik' => 75, 'Jejaring Kerja' => 70, 'Sosial Budaya' => 60, 'TIK' => 85, 'Profesionalisme' => 65, 'Anti Radikalisme' => 75,
        ];
        $pct2 = [
            'Nasionalisme' => 90, 'Integritas' => 40, 'Bela Negara' => 70, 'Pilar Negara' => 85, 'Bahasa Indonesia' => 65,
            'Kemampuan Verbal' => 45, 'Kemampuan Numerik' => 70, 'Kemampuan Figural' => 90,
            'Pelayanan Publik' => 85, 'Jejaring Kerja' => 65, 'Sosial Budaya' => 45, 'TIK' => 90, 'Profesionalisme' => 70, 'Anti Radikalisme' => 82,
        ];

        $sectionsMeta = [
            'TWK' => ['section' => $twkSection, 'bank' => $twkBank, 'scoringType' => 'single_correct', 'topics' => $this->keyByTopicId($twkTopics, $twkQuestions)],
            'TIU' => ['section' => $tiuSection, 'bank' => $tiuBank, 'scoringType' => 'single_correct', 'topics' => $this->keyByTopicId($tiuTopics, $tiuQuestions)],
            'TKP' => ['section' => $tkpSection, 'bank' => $tkpBank, 'scoringType' => 'weighted', 'topics' => $this->keyByTopicId($tkpTopics, $tkpQuestions)],
        ];

        $allTopicsByName = $twkTopics->union($tiuTopics)->union($tkpTopics);
        $pctByTopicId1 = $allTopicsByName->mapWithKeys(fn ($topic, $name) => [$topic->id => $pct1[$name]]);
        $pctByTopicId2 = $allTopicsByName->mapWithKeys(fn ($topic, $name) => [$topic->id => $pct2[$name]]);

        $attempt1 = $this->simulateAndScoreAttempt($admin, $exam, $sectionsMeta, now()->subDay(), $pctByTopicId1);
        $attempt2 = $this->simulateAndScoreAttempt($admin, $exam, $sectionsMeta, now(), $pctByTopicId2);

        // 11. Siswa dummy + ExamBatch + LeaderboardSnapshot (untuk fitur ranking)
        $dummyStudents = collect(range(1, 9))->map(fn ($i) => User::create([
            'name' => "Siswa Dummy {$i}",
            'email' => "siswa{$i}@example.com",
            'password' => bcrypt('password'),
            'role' => 'siswa',
            'is_active' => true,
            'email_verified_at' => now(),
        ]));

        $batch = new ExamBatch();
        $batch->forceFill([
            'exam_id' => $exam->id,
            'name' => 'Batch 1',
            'start_at' => now()->subDays(3),
            'end_at' => now()->subDays(2),
            'is_national' => true,
            'status' => 'ranked',
        ]);
        $batch->save();

        $adminScore = $attempt2->score;
        // 2 siswa lebih unggul dari admin, 7 di bawah -- admin jadi rank #3 dari 10.
        $offsets = [40, 15, -10, -25, -40, -55, -70, -85, -100];
        $totalParticipants = $dummyStudents->count() + 1;

        foreach ($dummyStudents as $i => $student) {
            $score = max(0, $adminScore + $offsets[$i]);
            $rank = $i < 2 ? $i + 1 : $i + 2; // lewati slot rank 3 (punya admin)
            $snap = new LeaderboardSnapshot();
            $snap->forceFill([
                'exam_batch_id' => $batch->id,
                'user_id' => $student->id,
                'score' => $score,
                'rank' => $rank,
                'percentile' => round((($totalParticipants - $rank) / $totalParticipants) * 100, 2),
                'correct_count' => random_int(50, 90),
                'duration_seconds' => random_int(3000, 5400),
                'generated_at' => now()->subDays(2),
            ]);
            $snap->save();
        }

        $adminSnap = new LeaderboardSnapshot();
        $adminSnap->forceFill([
            'exam_batch_id' => $batch->id,
            'user_id' => $admin->id,
            'score' => $adminScore,
            'rank' => 3,
            'percentile' => round((($totalParticipants - 3) / $totalParticipants) * 100, 2),
            'correct_count' => $attempt2->correct_count,
            'duration_seconds' => 5400,
            'generated_at' => now()->subDays(2),
        ]);
        $adminSnap->save();

        $this->command?->info("Selesai. Admin score attempt terbaru: {$adminScore}, rank #3 dari {$totalParticipants}.");
    }

    protected function createQuestionsPerTopic($bank, $topics, array $counts, string $scoringType): \Illuminate\Support\Collection
    {
        $result = collect();
        $topicList = $topics->values();

        foreach ($topicList as $i => $topic) {
            $count = $counts[$i];
            $questions = collect();

            for ($n = 1; $n <= $count; $n++) {
                $difficulties = ['mudah', 'sedang', 'sulit'];
                $question = Question::create([
                    'bank_id' => $bank->id,
                    'question_text' => "Soal {$topic->name} #{$n}: Manakah pernyataan yang paling tepat terkait {$topic->name}?",
                    'media_type' => 'none',
                    'type' => 'pg',
                    'difficulty' => $difficulties[array_rand($difficulties)],
                    'explanation' => "Pembahasan singkat untuk soal {$topic->name} #{$n}.",
                    'topic_id' => $topic->id,
                ]);

                $this->createOptions($question, $scoringType);
                $questions->push($question->load('options'));
            }

            $result->put($topic->id, $questions);
        }

        return $result;
    }

    protected function createOptions(Question $question, string $scoringType): void
    {
        if ($scoringType === 'weighted') {
            $pointsPool = [1, 2, 3, 4, 5];
            shuffle($pointsPool);
            foreach ($pointsPool as $i => $points) {
                QuestionOption::create([
                    'question_id' => $question->id,
                    'option_text' => 'Pilihan ' . chr(65 + $i),
                    'points' => $points,
                    'is_correct' => $points === 5,
                ]);
            }
            return;
        }

        $correctIndex = random_int(0, 4);
        for ($i = 0; $i < 5; $i++) {
            QuestionOption::create([
                'question_id' => $question->id,
                'option_text' => 'Pilihan ' . chr(65 + $i),
                'points' => $i === $correctIndex ? 5 : 0,
                'is_correct' => $i === $correctIndex,
            ]);
        }
    }

    protected function keyByTopicId($topicsByName, $questionsByTopicId): \Illuminate\Support\Collection
    {
        return $topicsByName->values()->mapWithKeys(fn ($topic) => [$topic->id => $questionsByTopicId->get($topic->id)]);
    }

    protected function simulateAndScoreAttempt(User $user, Exam $exam, array $sectionsMeta, Carbon $when, \Illuminate\Support\Collection $pctByTopicId): ExamAttempt
    {
        $attempt = new ExamAttempt();
        $attempt->forceFill([
            'user_id' => $user->id,
            'exam_id' => $exam->id,
            'status' => 'graded',
            'started_at' => $when->copy()->subMinutes(95),
            'finished_at' => $when,
            'tab_switch_count' => 0,
        ]);
        $attempt->save();

        $totalScore = 0;
        $totalCorrect = 0;

        foreach ($sectionsMeta as $meta) {
            $sectionRaw = 0;
            $sectionCorrect = 0;

            foreach ($meta['topics'] as $topicId => $questions) {
                $pct = $pctByTopicId[$topicId];
                $total = $questions->count();
                $correctTarget = (int) round($total * $pct / 100);
                $shuffled = $questions->shuffle()->values();

                $topicCorrect = 0;
                $topicRaw = 0;

                foreach ($shuffled as $idx => $question) {
                    $wantCorrect = $idx < $correctTarget;
                    $options = $question->options;
                    $selected = $wantCorrect
                        ? $options->firstWhere('is_correct', true)
                        : $options->where('is_correct', false)->random();

                    $isCorrect = (bool) $selected->is_correct;
                    $points = $meta['scoringType'] === 'weighted'
                        ? $selected->points
                        : ($isCorrect ? $meta['bank']->point_correct : $meta['bank']->point_wrong);

                    ExamAnswer::create([
                        'attempt_id' => $attempt->id,
                        'question_id' => $question->id,
                        'selected_option_id' => $selected->id,
                        'is_correct' => $isCorrect,
                        'needs_manual_grading' => false,
                    ]);

                    if ($isCorrect) {
                        $topicCorrect++;
                    }
                    $topicRaw += $points;
                }

                $topicScore = new ExamAttemptTopicScore();
                $topicScore->forceFill([
                    'exam_attempt_id' => $attempt->id,
                    'topic_id' => $topicId,
                    'correct_count' => $topicCorrect,
                    'total_count' => $total,
                    'raw_score' => $topicRaw,
                ]);
                $topicScore->save();

                $sectionCorrect += $topicCorrect;
                $sectionRaw += $topicRaw;
            }

            $sectionScore = new ExamAttemptSectionScore();
            $sectionScore->forceFill([
                'exam_attempt_id' => $attempt->id,
                'exam_section_id' => $meta['section']->id,
                'raw_score' => $sectionRaw,
                'correct_count' => $sectionCorrect,
                'passed_threshold' => $meta['section']->min_passing_score !== null
                    ? $sectionRaw >= $meta['section']->min_passing_score
                    : null,
            ]);
            $sectionScore->save();

            $totalScore += $sectionRaw;
            $totalCorrect += $sectionCorrect;
        }

        $attempt->forceFill(['score' => $totalScore, 'correct_count' => $totalCorrect]);
        $attempt->save();

        return $attempt;
    }
}

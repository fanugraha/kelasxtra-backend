<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptSectionScore;
use App\Models\ExamAttemptTopicScore;
use App\Models\ExamSection;
use App\Models\Package;
use App\Models\Program;
use App\Models\QuestionBank;
use App\Models\Taxonomy;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PerformanceSummaryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Bikin 1 program lengkap dengan 1 exam + 1 section (TWK) + question bank.
     * Dipakai bareng oleh hampir semua test di sini supaya rantai
     * Program -> QuestionBank -> Exam -> ExamSection -> Taxonomy konsisten.
     */
    protected function setupProgramWithExam(): array
    {
        $program = Program::factory()->create();

        $taxonomy = Taxonomy::create([
            'program_id' => $program->id,
            'type' => 'category',
            'code' => 'TWK',
            'name' => 'Tes Wawasan Kebangsaan',
        ]);

        $questionBank = QuestionBank::create([
            'program_id' => $program->id,
            'taxonomy_id' => $taxonomy->id,
            'title' => 'Bank Soal TWK',
            'scoring_type' => 'single_correct',
            'point_correct' => 5,
            'point_wrong' => 0,
        ]);

        $exam = Exam::factory()->create(['program_id' => $program->id]);

        $section = ExamSection::create([
            'exam_id' => $exam->id,
            'taxonomy_id' => $taxonomy->id,
            'question_bank_id' => $questionBank->id,
            'code' => 'TWK',
            'name' => 'Tes Wawasan Kebangsaan',
            'scoring_type' => 'single_correct',
            'min_passing_score' => 65,
        ]);

        return compact('program', 'taxonomy', 'questionBank', 'exam', 'section');
    }

    protected function makeAttempt(User $user, Exam $exam, int $score, ?Carbon $finishedAt = null): ExamAttempt
    {
        return ExamAttempt::factory()->create([
            'user_id' => $user->id,
            'exam_id' => $exam->id,
            'score' => $score,
            'finished_at' => $finishedAt ?? now(),
            'status' => 'graded',
        ]);
    }

    public function test_state_no_attempts_saat_user_belum_pernah_ujian_di_program_ini(): void
    {
        ['program' => $program] = $this->setupProgramWithExam();

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/me/performance-summary?program_id={$program->id}");

        $response->assertOk()
            ->assertJsonPath('state', 'no_attempts')
            ->assertJsonPath('sections', [])
            ->assertJsonPath('top_recommendations', [])
            ->assertJsonPath('cta.action_link', "/app/packages?program_id={$program->id}");

        // access key tidak ada sama sekali di response no_attempts
        $response->assertJsonMissingPath('access');
    }

    public function test_state_ready_dengan_topik_weak_medium_strong_dan_akses_penuh(): void
    {
        ['program' => $program, 'taxonomy' => $taxonomy, 'exam' => $exam, 'section' => $section]
            = $this->setupProgramWithExam();

        $package = Package::factory()->create(['program_id' => $program->id]);
        $user = User::factory()->create();

        // Enrollment aktif -> akses penuh
        Enrollment::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'status' => 'active',
            'start_date' => now()->subDay(),
            'end_date' => now()->addDays(30),
        ]);

        $topicWeak = Topic::create(['taxonomy_id' => $taxonomy->id, 'code' => 'T1', 'name' => 'Pancasila']);
        $topicMedium = Topic::create(['taxonomy_id' => $taxonomy->id, 'code' => 'T2', 'name' => 'UUD 1945']);
        $topicStrong = Topic::create(['taxonomy_id' => $taxonomy->id, 'code' => 'T3', 'name' => 'Bhinneka Tunggal Ika']);

        $attempt = $this->makeAttempt($user, $exam, 70);

        ExamAttemptSectionScore::create([
            'exam_attempt_id' => $attempt->id,
            'exam_section_id' => $section->id,
            'raw_score' => 70,
            'correct_count' => 14,
            'passed_threshold' => true,
        ]);

        // weak: 2/10 = 20%
        ExamAttemptTopicScore::create([
            'exam_attempt_id' => $attempt->id,
            'topic_id' => $topicWeak->id,
            'correct_count' => 2,
            'total_count' => 10,
        ]);
        // medium: 7/10 = 70%
        ExamAttemptTopicScore::create([
            'exam_attempt_id' => $attempt->id,
            'topic_id' => $topicMedium->id,
            'correct_count' => 7,
            'total_count' => 10,
        ]);
        // strong: 9/10 = 90%
        ExamAttemptTopicScore::create([
            'exam_attempt_id' => $attempt->id,
            'topic_id' => $topicStrong->id,
            'correct_count' => 9,
            'total_count' => 10,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/me/performance-summary?program_id={$program->id}");

        $response->assertOk()
            ->assertJsonPath('state', 'ready')
            ->assertJsonPath('access.full', true)
            ->assertJsonPath('access.upgrade_cta', null);

        $topics = collect($response->json('sections.0.topics'))->keyBy('topic_id');

        $this->assertSame('weak', $topics[$topicWeak->id]['level']);
        $this->assertSame(20, $topics[$topicWeak->id]['percentage']);

        $this->assertSame('medium', $topics[$topicMedium->id]['level']);
        $this->assertSame(70, $topics[$topicMedium->id]['percentage']);

        $this->assertSame('strong', $topics[$topicStrong->id]['level']);
        $this->assertSame(90, $topics[$topicStrong->id]['percentage']);

        // Rekomendasi cuma dari topik weak, urut priority_score
        $response->assertJsonCount(1, 'top_recommendations');
        $response->assertJsonPath('top_recommendations.0.topic_id', $topicWeak->id);
    }

    public function test_state_insufficient_attempts_kalau_60_persen_lebih_topik_masih_insufficient_data(): void
    {
        ['program' => $program, 'taxonomy' => $taxonomy, 'exam' => $exam, 'section' => $section]
            = $this->setupProgramWithExam();

        $package = Package::factory()->create(['program_id' => $program->id]);
        $user = User::factory()->create();

        // Enrollment aktif -> akses penuh, supaya breakdown topik (bukan
        // 'locked') yang dikembalikan dan bisa diverifikasi level-nya.
        Enrollment::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'status' => 'active',
            'start_date' => now()->subDay(),
            'end_date' => now()->addDays(30),
        ]);

        // 3 dari 3 topik akan insufficient_data (total_count < 5), jadi rasio 100% >= 60%
        $topics = collect(range(1, 3))->map(fn ($i) => Topic::create([
            'taxonomy_id' => $taxonomy->id,
            'code' => "T{$i}",
            'name' => "Topik {$i}",
        ]));

        $attempt = $this->makeAttempt($user, $exam, 50);

        ExamAttemptSectionScore::create([
            'exam_attempt_id' => $attempt->id,
            'exam_section_id' => $section->id,
            'raw_score' => 50,
            'correct_count' => 10,
            'passed_threshold' => false,
        ]);

        // Semua topik cuma dikerjakan 3 soal (< 5), jadi insufficient_data
        foreach ($topics as $topic) {
            ExamAttemptTopicScore::create([
                'exam_attempt_id' => $attempt->id,
                'topic_id' => $topic->id,
                'correct_count' => 2,
                'total_count' => 3,
            ]);
        }

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/me/performance-summary?program_id={$program->id}");

        $response->assertOk()->assertJsonPath('state', 'insufficient_attempts');

        foreach ($response->json('sections.0.topics') as $t) {
            $this->assertSame('insufficient_data', $t['level']);
        }
    }

    public function test_access_terkunci_kalau_user_tidak_punya_enrollment_sama_sekali(): void
    {
        ['program' => $program, 'taxonomy' => $taxonomy, 'exam' => $exam, 'section' => $section]
            = $this->setupProgramWithExam();

        $user = User::factory()->create();

        $topic = Topic::create(['taxonomy_id' => $taxonomy->id, 'code' => 'T1', 'name' => 'Pancasila']);

        $attempt = $this->makeAttempt($user, $exam, 70);

        ExamAttemptSectionScore::create([
            'exam_attempt_id' => $attempt->id,
            'exam_section_id' => $section->id,
            'raw_score' => 70,
            'correct_count' => 14,
            'passed_threshold' => true,
        ]);

        ExamAttemptTopicScore::create([
            'exam_attempt_id' => $attempt->id,
            'topic_id' => $topic->id,
            'correct_count' => 7,
            'total_count' => 10,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/me/performance-summary?program_id={$program->id}");

        $response->assertOk()
            ->assertJsonPath('access.full', false)
            ->assertJsonPath('access.upgrade_cta.action_link', "/app/packages?program_id={$program->id}")
            ->assertJsonPath('sections.0.topics.locked', true)
            ->assertJsonPath('top_recommendations', []);
    }

    /**
     * Temuan: AccessControlService::hasFullPerformanceAccess() punya kondisi
     * `orWhere('status', 'completed')` di komentarnya dijelaskan sebagai
     * "paket lama tetap bisa dilihat" -- tapi enum kolom enrollments.status
     * cuma ['pending', 'active', 'expired'], TIDAK PERNAH ada 'completed'.
     * Jadi kondisi itu kode mati. Test ini membuktikan: enrollment yang
     * SUDAH EXPIRED (skenario paling masuk akal untuk "paket lama") tetap
     * dianggap TIDAK punya akses penuh -- padahal comment-nya menyiratkan
     * seharusnya tetap bisa dilihat.
     *
     */
    public function test_enrollment_expired_dianggap_akses_penuh_sesuai_maksud_paket_lama_tetap_bisa_dilihat(): void
    {
        ['program' => $program, 'taxonomy' => $taxonomy, 'exam' => $exam, 'section' => $section]
            = $this->setupProgramWithExam();

        $package = Package::factory()->create(['program_id' => $program->id]);
        $user = User::factory()->create();

        Enrollment::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'status' => 'expired',
            'start_date' => now()->subDays(60),
            'end_date' => now()->subDays(10),
        ]);

        $topic = Topic::create(['taxonomy_id' => $taxonomy->id, 'code' => 'T1', 'name' => 'Pancasila']);
        $attempt = $this->makeAttempt($user, $exam, 70);

        ExamAttemptSectionScore::create([
            'exam_attempt_id' => $attempt->id,
            'exam_section_id' => $section->id,
            'raw_score' => 70,
            'correct_count' => 14,
            'passed_threshold' => true,
        ]);

        ExamAttemptTopicScore::create([
            'exam_attempt_id' => $attempt->id,
            'topic_id' => $topic->id,
            'correct_count' => 7,
            'total_count' => 10,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/me/performance-summary?program_id={$program->id}");

        // Setelah fix AccessControlService (orWhere status 'expired', bukan
        // 'completed' yang tidak pernah match enum): enrollment expired
        // sekarang benar-benar dianggap akses penuh, sesuai maksud comment
        // aslinya "paket lama tetap bisa dilihat".
        $response->assertOk()->assertJsonPath('access.full', true);
    }

    public function test_skor_section_diambil_dari_gabungan_beberapa_attempt_dalam_window(): void
    {
        ['program' => $program, 'taxonomy' => $taxonomy, 'exam' => $exam, 'section' => $section]
            = $this->setupProgramWithExam();

        $package = Package::factory()->create(['program_id' => $program->id]);
        $user = User::factory()->create();

        // Enrollment aktif -> akses penuh, supaya breakdown topik (bukan
        // 'locked') yang dikembalikan dan bisa diverifikasi.
        Enrollment::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'status' => 'active',
            'start_date' => now()->subDay(),
            'end_date' => now()->addDays(30),
        ]);

        $topic = Topic::create(['taxonomy_id' => $taxonomy->id, 'code' => 'T1', 'name' => 'Pancasila']);

        // Attempt lama: 3 soal topik ini
        $olderAttempt = $this->makeAttempt($user, $exam, 60, now()->subDay());
        ExamAttemptSectionScore::create([
            'exam_attempt_id' => $olderAttempt->id,
            'exam_section_id' => $section->id,
            'raw_score' => 60,
            'correct_count' => 12,
            'passed_threshold' => false,
        ]);
        ExamAttemptTopicScore::create([
            'exam_attempt_id' => $olderAttempt->id,
            'topic_id' => $topic->id,
            'correct_count' => 1,
            'total_count' => 3,
        ]);

        // Attempt terbaru: 3 soal lagi topik yang sama -> gabungan jadi 6 total (>= 5)
        $latestAttempt = $this->makeAttempt($user, $exam, 75, now());
        ExamAttemptSectionScore::create([
            'exam_attempt_id' => $latestAttempt->id,
            'exam_section_id' => $section->id,
            'raw_score' => 75,
            'correct_count' => 15,
            'passed_threshold' => true,
        ]);
        ExamAttemptTopicScore::create([
            'exam_attempt_id' => $latestAttempt->id,
            'topic_id' => $topic->id,
            'correct_count' => 2,
            'total_count' => 3,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/me/performance-summary?program_id={$program->id}&attempts_limit=5");

        $response->assertOk();

        $topicResult = collect($response->json('sections.0.topics'))->firstWhere('topic_id', $topic->id);

        // Gabungan: correct 1+2=3, total 3+3=6 -> 50%, sample_size 6 (bukan insufficient_data lagi)
        $this->assertSame(6, $topicResult['sample_size']);
        $this->assertSame(50, $topicResult['percentage']);
        $this->assertSame('weak', $topicResult['level']);

        // Section score-nya sendiri harus dari attempt TERBARU (raw_score 75), bukan yang lama
        $this->assertSame(75, $response->json('sections.0.current_score'));
    }

    public function test_attempt_dari_exam_latihan_topik_tidak_dihitung_sebagai_section_tersendiri(): void
    {
        ['program' => $program, 'taxonomy' => $taxonomy, 'exam' => $exam, 'section' => $section]
            = $this->setupProgramWithExam();

        $package = Package::factory()->create(['program_id' => $program->id]);
        $user = User::factory()->create();

        Enrollment::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'status' => 'active',
            'start_date' => now()->subDay(),
            'end_date' => now()->addDays(30),
        ]);

        $topicA = Topic::create(['taxonomy_id' => $taxonomy->id, 'code' => 'T1', 'name' => 'Pancasila']);
        $topicB = Topic::create(['taxonomy_id' => $taxonomy->id, 'code' => 'T2', 'name' => 'UUD 1945']);

        // Attempt tryout resmi -- topicA weak (20%)
        $tryoutAttempt = $this->makeAttempt($user, $exam, 40);
        ExamAttemptSectionScore::create([
            'exam_attempt_id' => $tryoutAttempt->id,
            'exam_section_id' => $section->id,
            'raw_score' => 40,
            'correct_count' => 8,
            'passed_threshold' => false,
        ]);
        ExamAttemptTopicScore::create([
            'exam_attempt_id' => $tryoutAttempt->id,
            'topic_id' => $topicA->id,
            'correct_count' => 2,
            'total_count' => 10,
        ]);

        // Exam Latihan Topik untuk topicB, meniru TopicPartGenerator: exam
        // punya topic_id (=> context otomatis jadi topic_practice lewat
        // Exam::booted()), dan ExamSection-nya taxonomy_id SAMA dengan
        // taxonomy TWK (persis pola bug yang ditemukan).
        $topicPracticeExam = Exam::factory()->create([
            'program_id' => $program->id,
            'topic_id' => $topicB->id,
            'part_number' => 1,
        ]);
        $this->assertSame('topic_practice', $topicPracticeExam->context);

        $topicPracticeSection = ExamSection::create([
            'exam_id' => $topicPracticeExam->id,
            'taxonomy_id' => $topicB->taxonomy_id,
            'code' => 'UUD 1945',
            'name' => 'UUD 1945',
            'scoring_type' => 'single_correct',
        ]);

        $practiceAttempt = $this->makeAttempt($user, $topicPracticeExam, 20);
        ExamAttemptSectionScore::create([
            'exam_attempt_id' => $practiceAttempt->id,
            'exam_section_id' => $topicPracticeSection->id,
            'raw_score' => 20,
            'correct_count' => 2,
            'passed_threshold' => false,
        ]);
        // topicB juga weak (20%) kalau ikut kehitung -- supaya kalau bug
        // muncul lagi, topicA dan topicB akan sama-sama nongol 2x di
        // top_recommendations (satu dari section asli, satu dari section
        // palsu latihan topik yang taxonomy_id-nya sama).
        ExamAttemptTopicScore::create([
            'exam_attempt_id' => $practiceAttempt->id,
            'topic_id' => $topicB->id,
            'correct_count' => 2,
            'total_count' => 10,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/me/performance-summary?program_id={$program->id}");

        $response->assertOk();

        // Cuma 1 section (TWK asli) -- attempt latihan topik tidak boleh
        // bikin section "UUD 1945" tersendiri.
        $response->assertJsonCount(1, 'sections');
        $response->assertJsonPath('sections.0.code', 'TWK');

        // top_recommendations tidak boleh berisi topic_id yang sama 2x.
        $topicIds = collect($response->json('top_recommendations'))->pluck('topic_id');
        $this->assertSame($topicIds->unique()->values()->all(), $topicIds->values()->all());
    }
}

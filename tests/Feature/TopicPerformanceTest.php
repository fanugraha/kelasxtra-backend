<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptTopicScore;
use App\Models\ExamSection;
use App\Models\Program;
use App\Models\QuestionBank;
use App\Models\Taxonomy;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TopicPerformanceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Sama persis dengan helper di PerformanceSummaryTest -- 1 program
     * lengkap dengan 1 exam + 1 section (TWK) + question bank, supaya rantai
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

    /**
     * Attempt selesai (default 'graded') dengan created_at yang bisa diatur --
     * dibutuhkan buat menguji urutan "terkini vs keseluruhan" (recent_percentage
     * & trend), yang disortir topicPerformance() berdasarkan created_at attempt.
     */
    protected function makeAttempt(User $user, Exam $exam, string $status = 'graded', ?Carbon $createdAt = null): ExamAttempt
    {
        return ExamAttempt::factory()->create([
            'user_id' => $user->id,
            'exam_id' => $exam->id,
            'status' => $status,
            'finished_at' => $status === 'in_progress' ? null : now(),
            'created_at' => $createdAt ?? now(),
        ]);
    }

    public function test_program_id_wajib_diisi(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/me/topic-performance');

        $response->assertStatus(422);
    }

    public function test_program_id_harus_program_yang_benar_benar_ada(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/me/topic-performance?program_id=999999');

        $response->assertStatus(422);
    }

    public function test_topics_kosong_kalau_user_belum_pernah_ujian_selesai_di_program_ini(): void
    {
        ['program' => $program] = $this->setupProgramWithExam();
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/me/topic-performance?program_id={$program->id}");

        $response->assertOk()
            ->assertJsonPath('attempts_included', 0)
            ->assertJsonPath('topics', []);
    }

    public function test_topik_bersample_cukup_tampil_dengan_persentase_dan_didahulukan_dari_topik_insufficient_data(): void
    {
        ['program' => $program, 'taxonomy' => $taxonomy, 'exam' => $exam] = $this->setupProgramWithExam();
        $user = User::factory()->create();

        $topicEnough = Topic::create(['taxonomy_id' => $taxonomy->id, 'code' => 'T1', 'name' => 'Pilar Negara']);
        $topicInsufficient = Topic::create(['taxonomy_id' => $taxonomy->id, 'code' => 'T2', 'name' => 'Bela Negara']);

        $attempt = $this->makeAttempt($user, $exam);

        ExamAttemptTopicScore::create([
            'exam_attempt_id' => $attempt->id,
            'topic_id' => $topicEnough->id,
            'correct_count' => 3,
            'total_count' => 10, // >= minSample (5) -> has_enough_data true, 30%
        ]);

        ExamAttemptTopicScore::create([
            'exam_attempt_id' => $attempt->id,
            'topic_id' => $topicInsufficient->id,
            'correct_count' => 1,
            'total_count' => 3, // < minSample (5) -> has_enough_data false, percentage null
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/me/topic-performance?program_id={$program->id}");
        $response->assertOk();

        $topics = $response->json('topics');

        $this->assertCount(2, $topics);

        $this->assertSame($topicEnough->id, $topics[0]['topic_id']);
        $this->assertTrue($topics[0]['has_enough_data']);
        $this->assertEquals(30.0, $topics[0]['percentage']);

        $this->assertSame($topicInsufficient->id, $topics[1]['topic_id']);
        $this->assertFalse($topics[1]['has_enough_data']);
        $this->assertNull($topics[1]['percentage']);
    }

    public function test_topik_terlemah_diurutkan_lebih_dulu_di_antara_topik_yang_sama_sama_bersample_cukup(): void
    {
        ['program' => $program, 'taxonomy' => $taxonomy, 'exam' => $exam] = $this->setupProgramWithExam();
        $user = User::factory()->create();

        $topicStrong = Topic::create(['taxonomy_id' => $taxonomy->id, 'code' => 'T1', 'name' => 'Kuat']);
        $topicWeak = Topic::create(['taxonomy_id' => $taxonomy->id, 'code' => 'T2', 'name' => 'Lemah']);

        $attempt = $this->makeAttempt($user, $exam);

        ExamAttemptTopicScore::create([
            'exam_attempt_id' => $attempt->id,
            'topic_id' => $topicStrong->id,
            'correct_count' => 8,
            'total_count' => 10, // 80%
        ]);

        ExamAttemptTopicScore::create([
            'exam_attempt_id' => $attempt->id,
            'topic_id' => $topicWeak->id,
            'correct_count' => 2,
            'total_count' => 10, // 20%
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/me/topic-performance?program_id={$program->id}");
        $response->assertOk();

        $topics = $response->json('topics');

        $this->assertSame($topicWeak->id, $topics[0]['topic_id']);
        $this->assertSame($topicStrong->id, $topics[1]['topic_id']);
    }

    public function test_trend_mencerminkan_selisih_performa_terkini_vs_keseluruhan(): void
    {
        ['program' => $program, 'taxonomy' => $taxonomy, 'exam' => $exam] = $this->setupProgramWithExam();
        $user = User::factory()->create();

        $topicUp = Topic::create(['taxonomy_id' => $taxonomy->id, 'code' => 'T1', 'name' => 'Membaik']);
        $topicDown = Topic::create(['taxonomy_id' => $taxonomy->id, 'code' => 'T2', 'name' => 'Menurun']);
        $topicStable = Topic::create(['taxonomy_id' => $taxonomy->id, 'code' => 'T3', 'name' => 'Stabil']);

        $older = $this->makeAttempt($user, $exam, 'graded', now()->subDays(5));
        $newer = $this->makeAttempt($user, $exam, 'graded', now());

        // topicUp: keseluruhan 6/10=60%, terkini (attempt terbaru) 5/5=100% -> diff +40 -> up
        ExamAttemptTopicScore::create(['exam_attempt_id' => $older->id, 'topic_id' => $topicUp->id, 'correct_count' => 1, 'total_count' => 5]);
        ExamAttemptTopicScore::create(['exam_attempt_id' => $newer->id, 'topic_id' => $topicUp->id, 'correct_count' => 5, 'total_count' => 5]);

        // topicDown: keseluruhan 6/10=60%, terkini 1/5=20% -> diff -40 -> down
        ExamAttemptTopicScore::create(['exam_attempt_id' => $older->id, 'topic_id' => $topicDown->id, 'correct_count' => 5, 'total_count' => 5]);
        ExamAttemptTopicScore::create(['exam_attempt_id' => $newer->id, 'topic_id' => $topicDown->id, 'correct_count' => 1, 'total_count' => 5]);

        // topicStable: keseluruhan 6/10=60%, terkini 3/5=60% -> diff 0 -> stable
        ExamAttemptTopicScore::create(['exam_attempt_id' => $older->id, 'topic_id' => $topicStable->id, 'correct_count' => 3, 'total_count' => 5]);
        ExamAttemptTopicScore::create(['exam_attempt_id' => $newer->id, 'topic_id' => $topicStable->id, 'correct_count' => 3, 'total_count' => 5]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/me/topic-performance?program_id={$program->id}");
        $response->assertOk();

        $topics = collect($response->json('topics'))->keyBy('topic_id');

        $this->assertSame('up', $topics[$topicUp->id]['trend']);
        $this->assertEquals(100.0, $topics[$topicUp->id]['recent_percentage']);

        $this->assertSame('down', $topics[$topicDown->id]['trend']);
        $this->assertEquals(20.0, $topics[$topicDown->id]['recent_percentage']);

        $this->assertSame('stable', $topics[$topicStable->id]['trend']);
        $this->assertEquals(60.0, $topics[$topicStable->id]['recent_percentage']);
    }

    public function test_attempt_in_progress_dan_program_lain_tidak_ikut_dihitung(): void
    {
        ['program' => $program1, 'taxonomy' => $taxonomy1, 'exam' => $exam1] = $this->setupProgramWithExam();
        ['exam' => $exam2] = $this->setupProgramWithExam(); // program lain

        $user = User::factory()->create();

        $topic = Topic::create(['taxonomy_id' => $taxonomy1->id, 'code' => 'T1', 'name' => 'Pilar Negara']);

        // Attempt masih in_progress di program1 -- harus DIABAIKAN.
        $inProgress = $this->makeAttempt($user, $exam1, 'in_progress');
        ExamAttemptTopicScore::create([
            'exam_attempt_id' => $inProgress->id,
            'topic_id' => $topic->id,
            'correct_count' => 0,
            'total_count' => 5,
        ]);

        // Attempt selesai tapi di EXAM PROGRAM LAIN -- harus DIABAIKAN karena
        // query topicPerformance() di-scope ke exam_id milik program1 saja.
        $otherProgramAttempt = $this->makeAttempt($user, $exam2, 'graded');
        ExamAttemptTopicScore::create([
            'exam_attempt_id' => $otherProgramAttempt->id,
            'topic_id' => $topic->id,
            'correct_count' => 5,
            'total_count' => 5,
        ]);

        // Attempt selesai di program1 -- INI SATU-SATUNYA yang harus terhitung.
        $validAttempt = $this->makeAttempt($user, $exam1, 'graded');
        ExamAttemptTopicScore::create([
            'exam_attempt_id' => $validAttempt->id,
            'topic_id' => $topic->id,
            'correct_count' => 4,
            'total_count' => 5,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/me/topic-performance?program_id={$program1->id}");
        $response->assertOk();

        $response->assertJsonPath('attempts_included', 1);

        $topics = $response->json('topics');
        $this->assertCount(1, $topics);
        $this->assertSame($topic->id, $topics[0]['topic_id']);
        $this->assertSame(4, $topics[0]['correct_count']);
        $this->assertSame(5, $topics[0]['total_count']);
    }
}

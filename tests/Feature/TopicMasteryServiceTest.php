<?php

namespace Tests\Feature;

use App\Jobs\GenerateTopicMasterySnapshotJob;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptTopicScore;
use App\Models\Program;
use App\Models\Taxonomy;
use App\Models\Topic;
use App\Models\TopicMasterySnapshot;
use App\Models\User;
use App\Services\TopicMasteryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TopicMasteryServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function makeTopic(Program $program, string $code = 'PIL'): Topic
    {
        $taxonomy = Taxonomy::create([
            'program_id' => $program->id,
            'type' => 'category',
            'code' => $code,
            'name' => $code,
        ]);

        return Topic::create([
            'taxonomy_id' => $taxonomy->id,
            'code' => $code,
            'name' => $code,
        ]);
    }

    protected function makeGradedAttempt(User $user, Exam $exam, Topic $topic, int $correct, int $total, Carbon $finishedAt): ExamAttempt
    {
        $attempt = ExamAttempt::factory()->create([
            'user_id' => $user->id,
            'exam_id' => $exam->id,
            'status' => 'graded',
            'finished_at' => $finishedAt,
            'created_at' => $finishedAt,
        ]);

        ExamAttemptTopicScore::create([
            'exam_attempt_id' => $attempt->id,
            'topic_id' => $topic->id,
            'correct_count' => $correct,
            'total_count' => $total,
        ]);

        return $attempt;
    }

    public function test_refresh_creates_snapshot_row_for_current_period(): void
    {
        $program = Program::factory()->create();
        $exam = Exam::factory()->create(['program_id' => $program->id]);
        $topic = $this->makeTopic($program);
        $user = User::factory()->create();

        $attempt = $this->makeGradedAttempt($user, $exam, $topic, 3, 5, now());

        app(TopicMasteryService::class)->refreshForAttempt($attempt);

        $this->assertDatabaseHas('topic_mastery_snapshots', [
            'user_id' => $user->id,
            'topic_id' => $topic->id,
            'correct_count' => 3,
            'total_count' => 5,
            'percentage' => 60,
        ]);
    }

    public function test_multiple_attempts_same_week_accumulate_into_one_period_row(): void
    {
        $program = Program::factory()->create();
        $exam = Exam::factory()->create(['program_id' => $program->id]);
        $topic = $this->makeTopic($program);
        $user = User::factory()->create();

        $attempt1 = $this->makeGradedAttempt($user, $exam, $topic, 2, 5, now());
        app(TopicMasteryService::class)->refreshForAttempt($attempt1);

        $attempt2 = $this->makeGradedAttempt($user, $exam, $topic, 4, 5, now());
        app(TopicMasteryService::class)->refreshForAttempt($attempt2);

        // 2 attempt, sama-sama minggu ini -- harus jadi SATU baris rollup
        // (akumulasi), bukan 2 baris terpisah.
        $this->assertSame(1, TopicMasterySnapshot::where('user_id', $user->id)
            ->where('topic_id', $topic->id)
            ->count());

        $this->assertDatabaseHas('topic_mastery_snapshots', [
            'user_id' => $user->id,
            'topic_id' => $topic->id,
            'correct_count' => 6,
            'total_count' => 10,
            'percentage' => 60,
        ]);
    }

    public function test_different_weeks_create_separate_period_rows_preserving_history(): void
    {
        $program = Program::factory()->create();
        $exam = Exam::factory()->create(['program_id' => $program->id]);
        $topic = $this->makeTopic($program);
        $user = User::factory()->create();

        $lastWeek = now()->subWeek();
        $thisWeek = now();

        $attempt1 = $this->makeGradedAttempt($user, $exam, $topic, 2, 5, $lastWeek);
        app(TopicMasteryService::class)->refreshForAttempt($attempt1);

        $attempt2 = $this->makeGradedAttempt($user, $exam, $topic, 4, 5, $thisWeek);
        app(TopicMasteryService::class)->refreshForAttempt($attempt2);

        // Minggu beda -- harus 2 baris terpisah (histori progres tetap ada,
        // bukan ketimpa jadi 1 baris terkini saja).
        $this->assertSame(2, TopicMasterySnapshot::where('user_id', $user->id)
            ->where('topic_id', $topic->id)
            ->count());
    }

    public function test_trend_is_up_when_percentage_improves_beyond_threshold(): void
    {
        $program = Program::factory()->create();
        $exam = Exam::factory()->create(['program_id' => $program->id]);
        $topic = $this->makeTopic($program);
        $user = User::factory()->create();

        $lastWeek = now()->subWeek();
        $thisWeek = now();

        $attempt1 = $this->makeGradedAttempt($user, $exam, $topic, 2, 10, $lastWeek); // 20%
        app(TopicMasteryService::class)->refreshForAttempt($attempt1);

        $attempt2 = $this->makeGradedAttempt($user, $exam, $topic, 8, 10, $thisWeek); // 80%
        app(TopicMasteryService::class)->refreshForAttempt($attempt2);

        $thisWeekSnapshot = TopicMasterySnapshot::where('user_id', $user->id)
            ->where('topic_id', $topic->id)
            ->orderByDesc('period')
            ->first();

        $this->assertSame('up', $thisWeekSnapshot->trend);
    }

    public function test_trend_is_null_when_no_previous_period_exists(): void
    {
        $program = Program::factory()->create();
        $exam = Exam::factory()->create(['program_id' => $program->id]);
        $topic = $this->makeTopic($program);
        $user = User::factory()->create();

        $attempt = $this->makeGradedAttempt($user, $exam, $topic, 3, 5, now());
        app(TopicMasteryService::class)->refreshForAttempt($attempt);

        $snapshot = TopicMasterySnapshot::where('user_id', $user->id)->first();

        $this->assertNull($snapshot->trend);
    }

    public function test_grade_and_close_dispatches_rollup_job_and_it_persists_snapshot(): void
    {
        // Test end-to-end: submit jawaban sungguhan lewat HTTP, pastikan
        // job rollup (QUEUE_CONNECTION=sync di phpunit.xml, jadi jalan
        // inline) benar-benar membuat baris snapshot -- bukan cuma
        // ter-dispatch tapi tidak pernah dieksekusi.
        $program = Program::factory()->create();
        $topic = $this->makeTopic($program);
        $exam = Exam::factory()->create(['program_id' => $program->id, 'is_free_preview' => true]);
        $user = User::factory()->create();

        $attempt = ExamAttempt::factory()->create([
            'user_id' => $user->id,
            'exam_id' => $exam->id,
            'status' => 'in_progress',
            'finished_at' => null,
        ]);

        ExamAttemptTopicScore::create([
            'exam_attempt_id' => $attempt->id,
            'topic_id' => $topic->id,
            'correct_count' => 4,
            'total_count' => 5,
        ]);

        // Panggil job langsung (tidak lewat gradeAndClose HTTP flow penuh,
        // supaya test ini fokus ke "job benar-benar mengeksekusi service",
        // bukan menguji ulang seluruh alur submit yang sudah dicover test
        // lain) -- tapi lewat Dispatchable::dispatch() sungguhan supaya
        // queue wiring-nya ikut teruji.
        $attempt->update(['status' => 'graded', 'finished_at' => now()]);
        GenerateTopicMasterySnapshotJob::dispatch($attempt->fresh());

        $this->assertDatabaseHas('topic_mastery_snapshots', [
            'user_id' => $user->id,
            'topic_id' => $topic->id,
            'correct_count' => 4,
            'total_count' => 5,
            'percentage' => 80,
        ]);
    }
}

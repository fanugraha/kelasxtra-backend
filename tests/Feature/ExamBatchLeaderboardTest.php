<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamBatch;
use App\Models\Program;
use App\Models\User;
use App\Services\LeaderboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExamBatchLeaderboardTest extends TestCase
{
    use RefreshDatabase;

    protected function makeBatch(array $overrides = []): ExamBatch
    {
        $program = Program::factory()->create();
        $exam = Exam::factory()->create(['program_id' => $program->id]);

        return ExamBatch::create(array_merge([
            'exam_id' => $exam->id,
            'name' => 'TO Nasional 1',
            'start_at' => now()->subDay(),
            'end_at' => now()->addDay(),
            'is_national' => true,
            'status' => 'ongoing',
        ], $overrides));
    }

    protected function makeAttempt(ExamBatch $batch, User $user, int $score, int $durationMinutes, ?Carbon $finishedAt = null): ExamAttempt
    {
        $finishedAt = $finishedAt ?? now();
        $startedAt = $finishedAt->copy()->subMinutes($durationMinutes);

        return ExamAttempt::factory()->create([
            'user_id' => $user->id,
            'exam_id' => $batch->exam_id,
            'exam_batch_id' => $batch->id,
            'score' => $score,
            'correct_count' => 10,
            'started_at' => $startedAt,
            'finished_at' => $finishedAt,
            'status' => 'graded',
        ]);
    }

    // ── LeaderboardService::generateForBatch() ──────────────────────────

    public function test_generate_mengurutkan_rank_berdasarkan_skor_tertinggi(): void
    {
        $batch = $this->makeBatch();

        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $userC = User::factory()->create();

        $this->makeAttempt($batch, $userA, 70, 30);
        $this->makeAttempt($batch, $userB, 90, 30);
        $this->makeAttempt($batch, $userC, 50, 30);

        app(LeaderboardService::class)->generateForBatch($batch);

        $this->assertDatabaseHas('leaderboard_snapshots', ['user_id' => $userB->id, 'rank' => 1, 'score' => 90]);
        $this->assertDatabaseHas('leaderboard_snapshots', ['user_id' => $userA->id, 'rank' => 2, 'score' => 70]);
        $this->assertDatabaseHas('leaderboard_snapshots', ['user_id' => $userC->id, 'rank' => 3, 'score' => 50]);

        $this->assertSame('ranked', $batch->fresh()->status);
    }

    public function test_generate_tie_break_skor_sama_dimenangkan_durasi_lebih_cepat(): void
    {
        $batch = $this->makeBatch();

        $userSlow = User::factory()->create();
        $userFast = User::factory()->create();

        // Skor sama (80), tapi userFast mengerjakan lebih cepat (20 menit vs 40 menit).
        $this->makeAttempt($batch, $userSlow, 80, 40);
        $this->makeAttempt($batch, $userFast, 80, 20);

        app(LeaderboardService::class)->generateForBatch($batch);

        $this->assertDatabaseHas('leaderboard_snapshots', ['user_id' => $userFast->id, 'rank' => 1]);
        $this->assertDatabaseHas('leaderboard_snapshots', ['user_id' => $userSlow->id, 'rank' => 2]);
    }

    public function test_generate_tie_break_skor_dan_durasi_sama_dimenangkan_yang_selesai_lebih_dulu(): void
    {
        $batch = $this->makeBatch();

        $userLater = User::factory()->create();
        $userEarlier = User::factory()->create();

        // Skor sama (80), durasi sama (30 menit), tapi userEarlier selesai lebih dulu.
        $this->makeAttempt($batch, $userLater, 80, 30, now());
        $this->makeAttempt($batch, $userEarlier, 80, 30, now()->subMinutes(10));

        app(LeaderboardService::class)->generateForBatch($batch);

        $this->assertDatabaseHas('leaderboard_snapshots', ['user_id' => $userEarlier->id, 'rank' => 1]);
        $this->assertDatabaseHas('leaderboard_snapshots', ['user_id' => $userLater->id, 'rank' => 2]);
    }

    public function test_generate_idempotent_tidak_menumpuk_snapshot_lama_saat_dijalankan_ulang(): void
    {
        $batch = $this->makeBatch();
        $user = User::factory()->create();

        $this->makeAttempt($batch, $user, 70, 30);

        app(LeaderboardService::class)->generateForBatch($batch);
        app(LeaderboardService::class)->generateForBatch($batch);

        $this->assertSame(1, \App\Models\LeaderboardSnapshot::where('exam_batch_id', $batch->id)->count());
    }

    public function test_generate_dengan_batch_tanpa_attempt_tetap_menandai_ranked(): void
    {
        $batch = $this->makeBatch();

        app(LeaderboardService::class)->generateForBatch($batch);

        $this->assertSame('ranked', $batch->fresh()->status);
        $this->assertSame(0, \App\Models\LeaderboardSnapshot::where('exam_batch_id', $batch->id)->count());
    }

    public function test_generate_hanya_menghitung_attempt_yang_sudah_selesai(): void
    {
        $batch = $this->makeBatch();
        $userDone = User::factory()->create();
        $userInProgress = User::factory()->create();

        $this->makeAttempt($batch, $userDone, 70, 30);

        ExamAttempt::factory()->create([
            'user_id' => $userInProgress->id,
            'exam_id' => $batch->exam_id,
            'exam_batch_id' => $batch->id,
            'score' => null,
            'started_at' => now()->subMinutes(10),
            'finished_at' => null,
            'status' => 'in_progress',
        ]);

        app(LeaderboardService::class)->generateForBatch($batch);

        $this->assertSame(1, \App\Models\LeaderboardSnapshot::where('exam_batch_id', $batch->id)->count());
        $this->assertDatabaseMissing('leaderboard_snapshots', ['user_id' => $userInProgress->id]);
    }

    // ── LeaderboardController ────────────────────────────────────────────

    public function test_index_menolak_kalau_batch_belum_ranked(): void
    {
        $batch = $this->makeBatch(['status' => 'ongoing']);
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson("/api/exam-batches/{$batch->id}/leaderboard");

        $response->assertStatus(422)
            ->assertJsonPath('batch_status', 'ongoing');
    }

    public function test_index_mengembalikan_top_50_terurut_rank(): void
    {
        $batch = $this->makeBatch();
        $user = User::factory()->create();
        $this->makeAttempt($batch, $user, 90, 30);

        app(LeaderboardService::class)->generateForBatch($batch);

        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson("/api/exam-batches/{$batch->id}/leaderboard");

        $response->assertOk();
        $this->assertCount(1, $response->json());
        $this->assertSame(1, $response->json()[0]['rank']);
    }

    public function test_my_position_menolak_kalau_batch_belum_ranked(): void
    {
        $batch = $this->makeBatch(['status' => 'ongoing']);
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson("/api/exam-batches/{$batch->id}/leaderboard/me");

        $response->assertStatus(422);
    }

    public function test_my_position_404_kalau_user_tidak_ikut_batch_ini(): void
    {
        $batch = $this->makeBatch();
        $participant = User::factory()->create();
        $this->makeAttempt($batch, $participant, 70, 30);

        app(LeaderboardService::class)->generateForBatch($batch);

        // User lain yang login TIDAK ikut batch ini.
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson("/api/exam-batches/{$batch->id}/leaderboard/me");

        $response->assertStatus(404);
    }

    public function test_my_position_mengembalikan_rank_dan_percentile_yang_benar(): void
    {
        $batch = $this->makeBatch();

        $userTop = User::factory()->create();
        $userMe = User::factory()->create();

        $this->makeAttempt($batch, $userTop, 100, 30);
        $this->makeAttempt($batch, $userMe, 50, 30);

        app(LeaderboardService::class)->generateForBatch($batch);

        Sanctum::actingAs($userMe);

        $response = $this->getJson("/api/exam-batches/{$batch->id}/leaderboard/me");

        $response->assertOk()
            ->assertJsonPath('rank', 2)
            ->assertJsonPath('total_peserta', 2)
            ->assertJsonPath('score', 50);
    }
}

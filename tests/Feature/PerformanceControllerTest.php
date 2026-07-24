<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamBatch;
use App\Models\LeaderboardSnapshot;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PerformanceControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_attempts_response_includes_null_streak_and_ranking(): void
    {
        $program = Program::factory()->create();
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson("/api/me/performance-summary?program_id={$program->id}");

        $response->assertOk()
            ->assertJsonPath('state', 'no_attempts')
            ->assertJsonPath('streak.count', 0)
            ->assertJsonPath('streak.active_today', false)
            ->assertJsonPath('ranking', null);
    }

    public function test_ready_response_includes_streak_and_ranking_data(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-01')->startOfWeek()->addDays(3)->setTime(12, 0));

        $program = Program::factory()->create();
        $user = User::factory()->create();
        $exam = Exam::factory()->create(['program_id' => $program->id]);

        // Cukup 1 attempt supaya lolos dari state no_attempts (buildSections
        // boleh kosong -- yang diuji di sini streak & ranking, bukan sections).
        ExamAttempt::factory()->create([
            'user_id' => $user->id,
            'exam_id' => $exam->id,
            'status' => 'graded',
            'finished_at' => now(),
        ]);

        $batch = ExamBatch::factory()->create(['exam_id' => $exam->id]);
        LeaderboardSnapshot::factory()->create([
            'exam_batch_id' => $batch->id,
            'user_id' => $user->id,
            'rank' => 4,
            'percentile' => 88.0,
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/me/performance-summary?program_id={$program->id}");

        $response->assertOk()
            ->assertJsonPath('streak.count', 1)
            ->assertJsonPath('streak.active_today', true)
            ->assertJsonPath('ranking.rank', 4)
            ->assertJsonPath('ranking.total_participants', 1)
            ->assertJsonPath('ranking.exam_batch_id', $batch->id);

        Carbon::setTestNow();
    }
}

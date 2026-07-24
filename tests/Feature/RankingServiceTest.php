<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamBatch;
use App\Models\LeaderboardSnapshot;
use App\Models\Program;
use App\Models\User;
use App\Services\RankingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RankingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected RankingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RankingService();
    }

    public function test_returns_null_when_user_has_no_snapshot_in_program(): void
    {
        $program = Program::factory()->create();
        $user = User::factory()->create();

        $result = $this->service->latestRanking($user, $program->id);

        $this->assertNull($result);
    }

    public function test_returns_latest_snapshot_with_total_participants(): void
    {
        $program = Program::factory()->create();
        $exam = Exam::factory()->create(['program_id' => $program->id]);
        $batch = ExamBatch::factory()->create(['exam_id' => $exam->id]);
        $user = User::factory()->create();

        LeaderboardSnapshot::factory()->create([
            'exam_batch_id' => $batch->id,
            'user_id' => $user->id,
            'rank' => 5,
            'percentile' => 92.5,
            'generated_at' => now()->subDay(),
        ]);

        LeaderboardSnapshot::factory()->count(9)->create([
            'exam_batch_id' => $batch->id,
        ]);

        $result = $this->service->latestRanking($user, $program->id);

        $this->assertNotNull($result);
        $this->assertSame(5, $result['rank']);
        $this->assertSame(10, $result['total_participants']);
        $this->assertEquals(92.5, $result['percentile']);
    }

    public function test_picks_the_most_recent_snapshot_across_multiple_batches(): void
    {
        $program = Program::factory()->create();
        $exam = Exam::factory()->create(['program_id' => $program->id]);
        $user = User::factory()->create();

        $olderBatch = ExamBatch::factory()->create(['exam_id' => $exam->id]);
        $newerBatch = ExamBatch::factory()->create(['exam_id' => $exam->id]);

        LeaderboardSnapshot::factory()->create([
            'exam_batch_id' => $olderBatch->id,
            'user_id' => $user->id,
            'rank' => 50,
            'generated_at' => now()->subDays(10),
        ]);

        LeaderboardSnapshot::factory()->create([
            'exam_batch_id' => $newerBatch->id,
            'user_id' => $user->id,
            'rank' => 3,
            'generated_at' => now()->subDay(),
        ]);

        $result = $this->service->latestRanking($user, $program->id);

        $this->assertSame(3, $result['rank']);
        $this->assertSame($newerBatch->id, $result['exam_batch_id']);
    }

    public function test_ignores_snapshots_from_other_programs(): void
    {
        $program = Program::factory()->create();
        $otherProgram = Program::factory()->create();

        $exam = Exam::factory()->create(['program_id' => $otherProgram->id]);
        $batch = ExamBatch::factory()->create(['exam_id' => $exam->id]);
        $user = User::factory()->create();

        LeaderboardSnapshot::factory()->create([
            'exam_batch_id' => $batch->id,
            'user_id' => $user->id,
        ]);

        $result = $this->service->latestRanking($user, $program->id);

        $this->assertNull($result);
    }
}

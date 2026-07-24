<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Program;
use App\Models\User;
use App\Services\StreakService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StreakServiceTest extends TestCase
{
    use RefreshDatabase;

    protected StreakService $service;
    protected Program $program;
    protected User $user;
    protected Carbon $today;

    protected function setUp(): void
    {
        parent::setUp();

        // Kamis tetap (dari Senin awal minggu manapun) supaya ada cukup hari
        // ke belakang di minggu kalender yang sama, tanpa bergantung tanggal
        // asli saat test dijalankan.
        $this->today = Carbon::parse('2026-01-01')->startOfWeek()->addDays(3)->setTime(12, 0);
        Carbon::setTestNow($this->today);

        $this->service = new StreakService();
        $this->program = Program::factory()->create();
        $this->user = User::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function attemptOn(Carbon $date): ExamAttempt
    {
        $exam = Exam::factory()->create(['program_id' => $this->program->id]);

        return ExamAttempt::factory()->create([
            'user_id' => $this->user->id,
            'exam_id' => $exam->id,
            'status' => 'graded',
            'finished_at' => $date->copy()->setTime(10, 0),
        ]);
    }

    public function test_no_attempts_gives_zero_streak(): void
    {
        $result = $this->service->currentStreak($this->user, $this->program->id);

        $this->assertSame(0, $result['count']);
        $this->assertFalse($result['active_today']);
    }

    public function test_consecutive_days_including_today(): void
    {
        $this->attemptOn($this->today->copy());
        $this->attemptOn($this->today->copy()->subDay());
        $this->attemptOn($this->today->copy()->subDays(2));

        $result = $this->service->currentStreak($this->user, $this->program->id);

        $this->assertSame(3, $result['count']);
        $this->assertTrue($result['active_today']);
    }

    public function test_grace_period_when_not_active_today_yet(): void
    {
        $this->attemptOn($this->today->copy()->subDay());
        $this->attemptOn($this->today->copy()->subDays(2));

        $result = $this->service->currentStreak($this->user, $this->program->id);

        $this->assertSame(2, $result['count']);
        $this->assertFalse($result['active_today']);
    }

    public function test_single_gap_bridged_by_weekly_freeze(): void
    {
        // Aktif hari ini & 2 hari lalu, bolong kemarin -- masih dalam jatah
        // freeze mingguan (1x), jadi streak tidak putus.
        $this->attemptOn($this->today->copy());
        $this->attemptOn($this->today->copy()->subDays(2));

        $result = $this->service->currentStreak($this->user, $this->program->id);

        $this->assertSame(2, $result['count']);
    }

    public function test_second_gap_in_same_week_breaks_streak(): void
    {
        // Aktif hari ini & 3 hari lalu (masih Senin di minggu sama), tapi
        // bolong 2 hari (kemarin & lusa) -- freeze cuma jatah 1x/minggu,
        // jadi gap kedua menghentikan hitungan mundur.
        $this->attemptOn($this->today->copy());
        $this->attemptOn($this->today->copy()->subDays(3));

        $result = $this->service->currentStreak($this->user, $this->program->id);

        $this->assertSame(1, $result['count']);
    }
}

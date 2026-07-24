<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Program;
use App\Models\Promo;
use App\Models\User;
use App\Notifications\PracticeLeaderboardRewardNotification;
use App\Services\PracticeLeaderboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Test PracticeLeaderboardService::generateForExam() -- ranking mingguan,
 * penentuan reward (badge_only vs voucher), dan generate Promo otomatis.
 *
 * CATATAN PENTING (temuan, bukan bug yang diperbaiki di sini):
 * generateForExam() MENGHAPUS lalu MEMBUAT ULANG seluruh entri leaderboard
 * setiap dipanggil (lihat komentar "Bersihkan entri lama ... idempotent" di
 * service). Tapi voucherCountThisWeek dihitung dari entri yang MASIH ADA saat
 * itu -- karena entri lama sudah dihapus duluan, memanggil generateForExam()
 * dua kali untuk exam+periode yang sama akan membuat Promo BARU lagi untuk
 * rank 1-3, bukan mendeteksi voucher yang sudah pernah diberikan sebelumnya.
 * Test di bawah TIDAK mengasumsikan idempotency untuk voucher -- ini murni
 * mendokumentasikan perilaku yang sebenarnya terjadi.
 */
class PracticeLeaderboardGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected function makeExam(): Exam
    {
        $program = Program::factory()->create();

        return Exam::factory()->create(['program_id' => $program->id]);
    }

    protected function makeFinishedAttempt(Exam $exam, User $user, int $score, ?\Carbon\Carbon $finishedAt = null): ExamAttempt
    {
        return ExamAttempt::create([
            'user_id' => $user->id,
            'exam_id' => $exam->id,
            'exam_batch_id' => null,
            'score' => $score,
            'correct_count' => 1,
            'started_at' => now()->subMinutes(30),
            'finished_at' => $finishedAt ?? now(),
            'status' => 'submitted',
        ]);
    }

    public function test_leaderboard_terbentuk_dan_rank_1_3_dapat_voucher_kalau_peserta_cukup(): void
    {
        Notification::fake();

        $exam = $this->makeExam();

        // min_participants_for_reward default = 10, jadi butuh >= 10 peserta
        // supaya reward voucher (bukan cuma badge) berlaku.
        $users = User::factory()->count(10)->create();

        foreach ($users as $index => $user) {
            // Skor menurun: user pertama (index 0) skor tertinggi -> rank 1
            $this->makeFinishedAttempt($exam, $user, score: 100 - ($index * 5));
        }

        app(PracticeLeaderboardService::class)->generateForExam($exam);

        $this->assertDatabaseCount('practice_leaderboards', 10);

        $rank1 = $exam->practiceLeaderboards()->where('ranking', 1)->first();
        $rank2 = $exam->practiceLeaderboards()->where('ranking', 2)->first();
        $rank3 = $exam->practiceLeaderboards()->where('ranking', 3)->first();
        $rank4 = $exam->practiceLeaderboards()->where('ranking', 4)->first();

        $this->assertEquals($users[0]->id, $rank1->user_id);
        $this->assertEquals(100, $rank1->skor_terbaik);

        // Rank 1-3 dapat voucher (peserta cukup: 10 >= min_participants_for_reward)
        $this->assertEquals('voucher_gold', $rank1->reward_type);
        $this->assertEquals('voucher_silver', $rank2->reward_type);
        $this->assertEquals('voucher_bronze', $rank3->reward_type);
        $this->assertNotNull($rank1->discount_code);

        // Rank 4 ke bawah tidak dapat reward apa pun
        $this->assertNull($rank4->reward_type);
        $this->assertNull($rank4->discount_code);

        // Promo beneran ter-generate untuk rank 1, terhubung ke leaderboard entry ini
        $this->assertDatabaseHas('promos', [
            'code' => $rank1->discount_code,
            'restricted_to_user_id' => $users[0]->id,
            'source' => 'leaderboard_reward',
            'leaderboard_entry_id' => $rank1->id,
        ]);

        Notification::assertSentTo($users[0], PracticeLeaderboardRewardNotification::class);
    }

    public function test_peserta_kurang_dari_minimum_hanya_dapat_badge_tanpa_voucher(): void
    {
        Notification::fake();

        $exam = $this->makeExam();

        // Cuma 3 peserta, di bawah min_participants_for_reward (default 10)
        $users = User::factory()->count(3)->create();

        foreach ($users as $index => $user) {
            $this->makeFinishedAttempt($exam, $user, score: 90 - ($index * 10));
        }

        app(PracticeLeaderboardService::class)->generateForExam($exam);

        $rank1 = $exam->practiceLeaderboards()->where('ranking', 1)->first();

        $this->assertEquals('badge_only', $rank1->reward_type);
        $this->assertNull($rank1->discount_code);

        // Tidak ada Promo yang ter-generate sama sekali dari reward ini
        $this->assertDatabaseCount('promos', 0);
    }

    public function test_skor_sama_persis_pemenangnya_yang_lebih_dulu_selesai(): void
    {
        $exam = $this->makeExam();

        $users = User::factory()->count(10)->create();

        // 2 user pertama skor SAMA PERSIS (100), tie-breaker: finished_at lebih awal menang
        $this->makeFinishedAttempt($exam, $users[0], score: 100, finishedAt: now()->subMinutes(5));
        $this->makeFinishedAttempt($exam, $users[1], score: 100, finishedAt: now()); // lebih telat

        foreach ($users->skip(2) as $index => $user) {
            $this->makeFinishedAttempt($exam, $user, score: 80 - $index);
        }

        app(PracticeLeaderboardService::class)->generateForExam($exam);

        $rank1 = $exam->practiceLeaderboards()->where('ranking', 1)->first();
        $rank2 = $exam->practiceLeaderboards()->where('ranking', 2)->first();

        $this->assertEquals($users[0]->id, $rank1->user_id, 'User yang lebih dulu selesai dengan skor sama harus rank 1.');
        $this->assertEquals($users[1]->id, $rank2->user_id);
    }

    public function test_hanya_skor_terbaik_user_yang_dipakai_kalau_attempt_berkali_kali(): void
    {
        $exam = $this->makeExam();

        $users = User::factory()->count(10)->create();

        // User pertama attempt 2x, skor terbaiknya yang harus dipakai (bukan skor pertama)
        $this->makeFinishedAttempt($exam, $users[0], score: 60, finishedAt: now()->subHours(2));
        $this->makeFinishedAttempt($exam, $users[0], score: 95, finishedAt: now()->subHour());

        foreach ($users->skip(1) as $index => $user) {
            $this->makeFinishedAttempt($exam, $user, score: 70 - $index);
        }

        app(PracticeLeaderboardService::class)->generateForExam($exam);

        // 1 entri per user per periode (bukan 2, meski attempt-nya 2 kali)
        $this->assertDatabaseCount('practice_leaderboards', 10);

        $entry = $exam->practiceLeaderboards()->where('user_id', $users[0]->id)->first();
        $this->assertEquals(95, $entry->skor_terbaik, 'Harus pakai skor TERBAIK, bukan skor attempt pertama.');
    }
}

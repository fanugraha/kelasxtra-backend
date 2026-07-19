<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\PracticeLeaderboard;
use App\Models\Promo;
use App\Notifications\PracticeLeaderboardRewardNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PracticeLeaderboardService
{
    /**
     * Proses ranking mingguan untuk 1 exam latihan soal (exam_batch_id kosong).
     * Dipanggil oleh job terjadwal, 1x per exam per minggu.
     */
    public function generateForExam(Exam $exam, ?Carbon $referenceDate = null): void
    {
        $referenceDate = $referenceDate ?? now();
        $periode = $this->formatPeriode($referenceDate);

        [$weekStart, $weekEnd] = $this->weekBoundaries($referenceDate);

        // 1. Ambil SEMUA attempt latihan soal (exam_batch_id NULL) yang selesai
        //    minggu ini, lengkap dengan finished_at -- dibutuhkan buat tie-breaker
        //    kalau ada skor yang sama persis antar siswa.
        $attempts = ExamAttempt::where('exam_id', $exam->id)
            ->whereNull('exam_batch_id')
            ->whereNotNull('score')
            ->whereNotNull('finished_at')
            ->whereIn('status', ['submitted', 'auto_submitted', 'graded'])
            ->whereBetween('finished_at', [$weekStart, $weekEnd])
            ->select('user_id', 'score', 'finished_at')
            ->get();

        if ($attempts->isEmpty()) {
            return;
        }

        // Per siswa: ambil skor TERBAIK-nya (kalau dia ngerjain berkali-kali).
        // Tie-breaker: kalau 2 siswa sama-sama capai skor tertinggi yang sama,
        // yang duluan mencapainya (finished_at lebih awal) menang -- bukan
        // berdasarkan urutan acak dari database.
        $bestAttempts = $attempts
            ->groupBy('user_id')
            ->map(function ($userAttempts, $userId) {
                $bestScore = $userAttempts->max('score');
                $earliestAtBestScore = $userAttempts
                    ->where('score', $bestScore)
                    ->sortBy('finished_at')
                    ->first();

                return (object) [
                    'user_id' => (int) $userId,
                    'best_score' => $bestScore,
                    'finished_at' => $earliestAtBestScore->finished_at,
                ];
            })
            ->sort(function ($a, $b) {
                if ($a->best_score !== $b->best_score) {
                    return $b->best_score <=> $a->best_score; // skor tinggi menang
                }
                return $a->finished_at <=> $b->finished_at; // skor sama -> lebih cepat menang
            })
            ->values();

        $totalPeserta = $bestAttempts->count();

        if ($totalPeserta === 0) {
            return;
        }

        $minPeserta = config('leaderboard.min_participants_for_reward');
        $rewardEligible = $totalPeserta >= $minPeserta;

        $now = Carbon::now();

        DB::transaction(function () use ($exam, $periode, $bestAttempts, $rewardEligible, $now) {
            // Bersihkan entri lama untuk exam + periode ini (idempotent)
            PracticeLeaderboard::where('exam_id', $exam->id)
                ->where('periode', $periode)
                ->delete();

            foreach ($bestAttempts as $index => $row) {
                $rank = $index + 1;
                $userId = $row->user_id;

                $entry = PracticeLeaderboard::create([
                    'exam_id' => $exam->id,
                    'user_id' => $userId,
                    'periode' => $periode,
                    'skor_terbaik' => $row->best_score,
                    'ranking' => $rank,
                    'reward_type' => null,
                    'discount_code' => null,
                ]);

                if ($rank <= 3) {
                    $this->assignReward($entry, $rank, $periode, $rewardEligible, $now);
                }
            }
        });
    }

    /**
     * Tentukan reward untuk 1 entri Rank 1-3: badge saja, atau badge + voucher
     * (tergantung syarat minimal peserta & jatah voucher mingguan siswa).
     */
    protected function assignReward(PracticeLeaderboard $entry, int $rank, string $periode, bool $rewardEligible, Carbon $now): void
    {
        $rewardTypeMap = [
            1 => 'voucher_gold',
            2 => 'voucher_silver',
            3 => 'voucher_bronze',
        ];

        // Syarat 1: exam harus punya peserta cukup
        if (! $rewardEligible) {
            $entry->update(['reward_type' => 'badge_only']);
            $entry->user->notify(new PracticeLeaderboardRewardNotification($entry));
            return;
        }

        // Syarat 2: cek jatah voucher user minggu ini
        $maxVoucherPerWeek = config('leaderboard.max_voucher_per_user_per_week');

        $voucherCountThisWeek = PracticeLeaderboard::where('user_id', $entry->user_id)
            ->where('periode', $periode)
            ->whereNotNull('discount_code')
            ->count();

        if ($voucherCountThisWeek >= $maxVoucherPerWeek) {
            $entry->update(['reward_type' => 'badge_only']);
            $entry->user->notify(new PracticeLeaderboardRewardNotification($entry));
            return;
        }

        // Lolos semua syarat -> generate voucher promo
        $rewardAmount = config("leaderboard.reward_amounts.{$rank}");
        $validDays = config('leaderboard.voucher_valid_days');
        $code = $this->generateUniqueCode();

        $promo = Promo::create([
            'title' => "Reward Rank {$rank} - Latihan Soal - {$periode}",
            'description' => 'Reward otomatis dari leaderboard latihan soal mingguan.',
            'discount_type' => 'fixed',
            'discount_value' => $rewardAmount,
            'max_discount_amount' => $rewardAmount,
            'code' => $code,
            'usage_limit_per_user' => 1,
            'total_quota' => 1,
            'new_user_only' => false,
            'is_active' => true,
            'valid_from' => $now,
            'valid_until' => $now->copy()->addDays($validDays),
            'restricted_to_user_id' => $entry->user_id,
            'source' => 'leaderboard_reward',
            'leaderboard_entry_id' => $entry->id,
        ]);

        $entry->update([
            'reward_type' => $rewardTypeMap[$rank],
            'discount_code' => $promo->code,
        ]);

        $entry->user->notify(new PracticeLeaderboardRewardNotification($entry->fresh()));
    }

    /**
     * Generate kode promo unik, format: WIN-XXXXXX (6 karakter acak).
     */
    protected function generateUniqueCode(): string
    {
        do {
            $code = 'WIN-' . strtoupper(Str::random(6));
        } while (Promo::where('code', $code)->exists());

        return $code;
    }

    /**
     * Format periode ISO week, contoh: "2026-W29"
     */
    protected function formatPeriode(Carbon $date): string
    {
        return $date->format('o') . '-W' . str_pad((string) $date->isoWeek(), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Rentang awal-akhir minggu ISO (Senin 00:00 - Minggu 23:59:59) untuk tanggal referensi.
     */
    protected function weekBoundaries(Carbon $date): array
    {
        $start = $date->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $end = $date->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();

        return [$start, $end];
    }
}

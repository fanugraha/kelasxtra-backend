<?php

namespace App\Services;

use App\Models\ExamAttempt;
use App\Models\ExamAttemptTopicScore;
use App\Models\TopicMasterySnapshot;
use Illuminate\Support\Carbon;

/**
 * Read-path rollup untuk performa per topik. Menggantikan agregasi
 * on-the-fly yang sekarang dipakai ExamController::topicPerformance()
 * (belum diganti di PR ini -- sengaja, lihat catatan di bawah).
 *
 * Kenapa time-series per periode (bukan 1 baris per user+topic yang
 * di-overwrite): supaya dashboard orang tua & recommendation engine bisa
 * menunjukkan PROGRES ("naik dari 40% ke 65% dalam sebulan"), bukan cuma
 * foto kondisi terkini -- itu yang membuat fitur ini terasa layak
 * dibayar berlangganan terus, bukan cuma dicek sekali lalu di-churn.
 *
 * CATATAN SCOPE: PR ini baru membangun WRITE PATH (rollup terisi dan
 * ter-update tiap attempt selesai). ExamController::topicPerformance()
 * (READ PATH, dipakai dashboard siswa sekarang) SENGAJA belum diarahkan
 * ke tabel ini -- itu perubahan lebih berisiko (mengganti sumber data
 * endpoint yang sudah teruji 7 test kasus) yang lebih aman dilakukan
 * setelah rollup ini berjalan beberapa waktu dan datanya terbukti benar,
 * bukan digabung sekaligus dengan pembuatan tabel barunya.
 */
class TopicMasteryService
{
    /**
     * Threshold persentase untuk membedakan trend 'up'/'down' dari
     * 'stable' -- mencegah noise (naik/turun 1-2 poin karena kebetulan
     * urutan soal) dianggap sebagai tren yang berarti.
     */
    protected const TREND_THRESHOLD = 5;

    /**
     * Refresh rollup untuk semua topik yang disentuh oleh 1 attempt yang
     * baru selesai di-grade. Dipanggil async lewat GenerateTopicMasterySnapshotJob
     * (bukan inline di request submit ujian) supaya momen submit ujian --
     * momen paling sensitif buat siswa -- tidak ikut menanggung beban ini.
     */
    public function refreshForAttempt(ExamAttempt $attempt): void
    {
        $referenceDate = $attempt->finished_at ?? now();
        $topicIds = ExamAttemptTopicScore::where('exam_attempt_id', $attempt->id)
            ->pluck('topic_id')
            ->unique();

        foreach ($topicIds as $topicId) {
            $this->refreshForUserTopic($attempt->user_id, $topicId, $referenceDate);
        }
    }

    /**
     * Refresh 1 baris rollup (user, topic, periode minggu ini) dari
     * SELURUH attempt milik user ini yang finished_at jatuh di minggu
     * yang sama -- bukan cuma dari 1 attempt yang baru masuk, supaya
     * kalau siswa mengerjakan >1 attempt di topik yang sama dalam
     * minggu yang sama, angka periode ini tetap akumulasi yang benar.
     */
    protected function refreshForUserTopic(int $userId, int $topicId, Carbon $referenceDate): void
    {
        $period = $this->formatPeriode($referenceDate);
        [$weekStart, $weekEnd] = $this->weekBoundaries($referenceDate);

        $attemptIds = ExamAttempt::where('user_id', $userId)
            ->whereBetween('finished_at', [$weekStart, $weekEnd])
            ->whereIn('status', ['submitted', 'auto_submitted', 'graded'])
            ->pluck('id');

        $totals = ExamAttemptTopicScore::whereIn('exam_attempt_id', $attemptIds)
            ->where('topic_id', $topicId)
            ->selectRaw('SUM(correct_count) as correct, SUM(total_count) as total')
            ->first();

        $correct = (int) ($totals->correct ?? 0);
        $total = (int) ($totals->total ?? 0);

        if ($total === 0) {
            return;
        }

        $percentage = (int) round(($correct / $total) * 100);

        $previous = TopicMasterySnapshot::where('user_id', $userId)
            ->where('topic_id', $topicId)
            ->where('period', '<', $period)
            ->orderByDesc('period')
            ->first();

        $trend = $this->computeTrend($percentage, $previous?->percentage);

        TopicMasterySnapshot::updateOrCreate(
            ['user_id' => $userId, 'topic_id' => $topicId, 'period' => $period],
            [
                'correct_count' => $correct,
                'total_count' => $total,
                'percentage' => $percentage,
                'trend' => $trend,
                'computed_at' => now(),
            ]
        );
    }

    protected function computeTrend(int $currentPercentage, ?int $previousPercentage): ?string
    {
        if ($previousPercentage === null) {
            return null;
        }

        $diff = $currentPercentage - $previousPercentage;

        if ($diff >= self::TREND_THRESHOLD) {
            return TopicMasterySnapshot::TREND_UP;
        }

        if ($diff <= -self::TREND_THRESHOLD) {
            return TopicMasterySnapshot::TREND_DOWN;
        }

        return TopicMasterySnapshot::TREND_STABLE;
    }

    /**
     * Format periode ISO week -- SAMA PERSIS dengan
     * PracticeLeaderboardService::formatPeriode(), supaya konvensi
     * penamaan periode konsisten di seluruh codebase.
     */
    protected function formatPeriode(Carbon $date): string
    {
        return $date->format('o').'-W'.str_pad((string) $date->isoWeek(), 2, '0', STR_PAD_LEFT);
    }

    protected function weekBoundaries(Carbon $date): array
    {
        $start = $date->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $end = $date->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();

        return [$start, $end];
    }
}

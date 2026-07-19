<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\ExamBatch;
use App\Models\Exam;
use App\Jobs\GenerateLeaderboardJob;
use App\Jobs\GeneratePracticeLeaderboardJob;

/*
|--------------------------------------------------------------------------
| Console Routes & Task Scheduler
|--------------------------------------------------------------------------
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * AUTOMATISASI LEADERBOARD TRY OUT NASIONAL (Section 7 Desain MVP)
 *
 * Mengecek setiap jam apakah ada Exam Batch yang sudah melewati window
 * pengerjaan (end_at) tapi statusnya masih 'ongoing'. Kalau ada, dorong
 * proses hitung ranking & percentile massal ke Laravel Queue.
 *
 * CATATAN: status 'active' sebelumnya dipakai di sini TIDAK PERNAH COCOK
 * dengan enum asli exam_batches.status (scheduled/ongoing/finished/ranked)
 * — jadi scheduler ini sebelumnya tidak pernah benar-benar jalan. Diperbaiki
 * jadi 'ongoing'.
 */
Schedule::call(function () {
    $expiredBatches = ExamBatch::where('end_at', '<=', now())
        ->where('status', 'ongoing')
        ->get();

    foreach ($expiredBatches as $batch) {
        GenerateLeaderboardJob::dispatch($batch);
    }
})->hourly();

/**
 * REKONSILIASI TRANSAKSI PENDING (jaga-jaga webhook Midtrans gagal/telat)
 * Jalan tiap 30 menit — cek ulang langsung ke Midtrans Status API untuk
 * transaksi pending yang sudah lewat batas waktu wajar.
 */
Schedule::command('transactions:reconcile')->everyThirtyMinutes();

/**
 * PEMBERSIHAN LEADERBOARD_EVENTS (retensi 30 hari)
 * Jalan tiap hari jam 03:00 -- hapus event rank-change yang lebih tua dari
 * 30 hari supaya tabel tidak menggembung (event ini cuma dipakai untuk
 * notifikasi toast jangka pendek, tidak perlu disimpan lama).
 */
Schedule::command('leaderboard:prune-events')->dailyAt('03:00');

/**
 * AUTOMATISASI LEADERBOARD LATIHAN SOAL MINGGUAN
 *
 * Jalan tiap Minggu jam 23:55 (akhir periode ISO week). Loop ke semua Exam
 * yang punya minimal 1 attempt latihan soal (exam_batch_id NULL), lalu
 * proses ranking + reward mingguan lewat PracticeLeaderboardService.
 */
Schedule::call(function () {
    $examIds = Exam::whereHas('attempts', function ($query) {
        $query->whereNull('exam_batch_id');
    })->pluck('id');

    foreach ($examIds as $examId) {
        GeneratePracticeLeaderboardJob::dispatch(Exam::find($examId));
    }
})->weeklyOn(0, '23:55');
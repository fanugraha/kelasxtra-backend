<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\ExamBatch;
use App\Jobs\GenerateLeaderboardJob;

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
<?php

namespace App\Support\Leaderboard;

use Illuminate\Support\Facades\Cache;

/**
 * Pola locking bersama untuk SEMUA generator leaderboard (try-out batch,
 * latihan mingguan, dan jenis leaderboard baru ke depan).
 *
 * Kenapa ini perlu di-share, bukan ditulis ulang tiap service: tanpa lock,
 * dua proses regenerate leaderboard yang sama nyaris bersamaan (mis. job
 * terjadwal + admin klik "regenerate manual" di waktu yang sama) bisa saling
 * baca "state lama" yang sama sebelum salah satu selesai menulis, sehingga
 * rank/reward yang dihasilkan tidak akurat. Sebelum kelas ini dibuat, cuma
 * PracticeLeaderboardService yang punya proteksi ini -- LeaderboardService
 * (try-out batch) tidak, walau exposed ke race condition yang sama persis
 * kalau generateForBatch() ke-trigger dobel.
 */
class LeaderboardLock
{
    /**
     * Jalankan $callback di bawah lock bernama $key. Blocking selama maksimal
     * $waitSeconds menunggu lock lain selesai, lock itu sendiri auto-release
     * setelah $lockSeconds kalau proses di dalamnya macet/gagal release.
     */
    public static function run(string $key, callable $callback, int $lockSeconds = 30, int $waitSeconds = 10): mixed
    {
        return Cache::lock($key, $lockSeconds)->block($waitSeconds, $callback);
    }
}

<?php

namespace App\Services;

use App\Models\ExamBatch;
use App\Models\LeaderboardSnapshot;
use App\Models\ExamAttempt;
use App\Support\Leaderboard\LeaderboardLock;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class LeaderboardService
{
    public function generateForBatch(ExamBatch $batch): void
    {
        // Lock per batch -- pola sama seperti PracticeLeaderboardService,
        // lewat LeaderboardLock bersama. Mencegah race condition kalau
        // generateForBatch() untuk batch yang sama ke-trigger dobel nyaris
        // bersamaan (mis. job terjadwal bentrok dengan admin klik
        // "regenerate" manual di waktu yang sama).
        LeaderboardLock::run("leaderboard:batch:{$batch->id}", function () use ($batch) {
            $this->doGenerateForBatch($batch);
        });
    }

    protected function doGenerateForBatch(ExamBatch $batch): void
    {
        // 1. Serahkan hitungan durasi dan tie-breaking ke Database Engine (Jauh lebih cepat & hemat RAM)
        $attempts = ExamAttempt::where('exam_batch_id', $batch->id)
            ->whereNotNull('score')
            ->whereNotNull('finished_at')
            ->whereIn('status', ['submitted', 'auto_submitted', 'graded'])
            ->select('*')
            ->selectRaw('TIMESTAMPDIFF(SECOND, started_at, finished_at) as duration_seconds')
            // Aturan Tie-Breaking Resmi MVP
            ->orderBy('score', 'desc')
            ->orderBy('duration_seconds', 'asc')
            ->orderBy('finished_at', 'asc')
            ->get();

        $total = $attempts->count();

        if ($total === 0) {
            $batch->update(['status' => 'ranked']);
            return;
        }

        $now = Carbon::now();
        $snapshots = [];

        // 2. Siapkan data array untuk proses Bulk Insert
        foreach ($attempts as $index => $attempt) {
            $rank = $index + 1;
            $percentile = round((($total - $rank) / $total) * 100, 2);

            $snapshots[] = [
                'exam_batch_id'    => $batch->id,
                'user_id'          => $attempt->user_id,
                'score'            => $attempt->score,
                'rank'             => $rank,
                'percentile'       => $percentile,
                'correct_count'    => $attempt->correct_count ?? 0,
                'duration_seconds' => $attempt->duration_seconds,
                'generated_at'     => $now,
                'created_at'       => $now,
                'updated_at'       => $now,
            ];
        }

        // 3. Eksekusi secara Idempotent High-Performance dengan Database Transaction
        DB::transaction(function () use ($batch, $snapshots) {
            // Hapus snapshot lama untuk batch ini dalam 1 query tunggal
            LeaderboardSnapshot::where('exam_batch_id', $batch->id)->delete();

            // Potong data menjadi chunk (per 200 baris) agar menghemat batasan payload raw SQL
            foreach (array_chunk($snapshots, 200) as $chunk) {
                LeaderboardSnapshot::insert($chunk);
            }

            // Buka pintu API Leaderboard untuk siswa
            $batch->update(['status' => 'ranked']);
        });
    }
}
<?php

namespace App\Jobs;

use App\Models\ExamBatch;
use App\Models\LeaderboardSnapshot;
use App\Models\ExamAttempt;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class GenerateLeaderboardSnapshot implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $batchId;

    /**
     * Create a new job instance.
     */
    public function __construct($batchId)
    {
        $this->batchId = $batchId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $batch = ExamBatch::find($this->batchId);
        if (!$batch) return;

        // 1. Ambil seluruh attempt yang sah (status: graded / submitted) dalam batch ini
        // Urutkan berdasarkan aturan TIE-BREAKING MVP:
        // - Score DESC (Tertinggi)
        // - Duration ASC (Tercepat) -> dihitung lewat TIMESTAMPDIFF
        // - Finished_at ASC (Submit Terawal)
        $attempts = ExamAttempt::where('exam_batch_id', $this->batchId)
            ->whereIn('status', ['graded', 'submitted'])
            ->select('*')
            ->selectRaw('TIMESTAMPDIFF(SECOND, started_at, finished_at) as duration_seconds')
            ->orderBy('score', 'desc')
            ->orderBy('duration_seconds', 'asc')
            ->orderBy('finished_at', 'asc')
            ->get();

        $totalParticipants = $attempts->count();
        if ($totalParticipants === 0) return;

        // 2. Bersihkan snapshot lama untuk batch ini (Idempotent)
        LeaderboardSnapshot::where('exam_batch_id', $this->batchId)->delete();

        // 3. Loop untuk hitung Rank & Percentile
        $snapshots = [];
        foreach ($attempts as $index => $attempt) {
            $rank = $index + 1;

            // Hitung Percentile (Rumus Dasar: ((Total - Rank) / Total) * 100)
            // Contoh: Rank 1 dari 100 -> ((100-1)/100)*100 = Top 99% (Sistem Percentile Nasional)
            $percentile = $totalParticipants > 1 
                ? round((($totalParticipants - $rank) / $totalParticipants) * 100, 2)
                : 100.00;

            $snapshots[] = [
                'exam_batch_id' => $this->batchId,
                'user_id'       => $attempt->user_id,
                'score'         => $attempt->score,
                'rank'          => $rank,
                'percentile'    => $percentile,
                'correct_count' => $attempt->correct_count,
                'duration_seconds' => $attempt->duration_seconds,
                'generated_at'  => now(),
                'created_at'    => now(),
                'updated_at'    => now(),
            ];
        }

        // 4. Bulk Insert ke database demi performa tinggi (menghindari ribuan query tunggal)
        foreach (array_chunk($snapshots, 200) as $chunk) {
            LeaderboardSnapshot::insert($chunk);
        }

        // 5. Update status batch ujian menjadi selesai/calculated jika diperlukan
        $batch->update(['status' => 'calculated']);
    }
}
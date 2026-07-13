<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamBatch;
use Illuminate\Http\Request;

/**
 * Section 7 desain MVP: "Tampilan leaderboard ke siswa: ranking nasional +
 * posisi & percentile pribadi. Data diambil murni dari leaderboard_snapshots."
 */
class LeaderboardController extends Controller
{
    /**
     * GET /api/exam-batches/{examBatch}/leaderboard
     * Ranking nasional/kelas untuk satu batch (Top 50 demi optimasi beban network).
     */
    public function index(Request $request, ExamBatch $examBatch)
    {
        // Sinkronisasi status: Batch dianggap valid jika bernilai 'ranked'
        if ($examBatch->status !== 'ranked') {
            return response()->json([
                'message' => 'Leaderboard belum tersedia untuk batch ini.',
                'batch_status' => $examBatch->status,
            ], 422);
        }

        $snapshots = $examBatch->leaderboardSnapshots()
            ->with('user:id,name,level_pendidikan')
            ->orderBy('rank')
            ->take(50) // Batasi 50 besar untuk performa React app yang gesit
            ->get();

        return response()->json($snapshots);
    }

    /**
     * GET /api/exam-batches/{examBatch}/leaderboard/me
     * Posisi & percentile pribadi siswa yang sedang login.
     */
    public function myPosition(Request $request, ExamBatch $examBatch)
    {
        if ($examBatch->status !== 'ranked') {
            return response()->json([
                'message' => 'Leaderboard belum tersedia untuk batch ini.',
                'batch_status' => $examBatch->status,
            ], 422);
        }

        $snapshot = $examBatch->leaderboardSnapshots()
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $snapshot) {
            return response()->json([
                'message' => 'Anda tidak mengikuti try out batch ini atau belum dinilai.',
            ], 404);
        }

        $totalPeserta = $examBatch->leaderboardSnapshots()->count();

        // Interpretasi Top % Nasional (misal Percentile 95.00 berarti siswa masuk Top 5% Nasional)
        $topPercentile = count($examBatch->leaderboardSnapshots) > 0 
            ? round(100 - $snapshot->percentile, 2) 
            : 0;

        return response()->json([
            'rank' => $snapshot->rank,
            'total_peserta' => $totalPeserta,
            'percentile' => $snapshot->percentile,
            'score' => $snapshot->score,
            'correct_count' => $snapshot->correct_count,
            'duration_seconds' => $snapshot->duration_seconds,
            'summary_text' => "Rank {$snapshot->rank} dari {$totalPeserta} peserta — Top {$topPercentile}% Nasional"
        ]);
    }
}
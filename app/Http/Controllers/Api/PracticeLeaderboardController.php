<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PracticeLeaderboardController extends Controller
{
    /**
     * GET /api/exams/{exam}/leaderboard
     * Ranking latihan soal periode berjalan (minggu ini), Top 50.
     */
    public function index(Request $request, Exam $exam)
    {
        $periode = $this->currentPeriode();

        $entries = $exam->practiceLeaderboards()
            ->with('user:id,name,level_pendidikan')
            ->where('periode', $periode)
            ->orderBy('ranking')
            ->take(50)
            ->get();

        return response()->json([
            'periode' => $periode,
            'data' => $entries,
        ]);
    }

    /**
     * GET /api/exams/{exam}/leaderboard/me
     * Posisi siswa yang login di periode berjalan (minggu ini).
     */
    public function myPosition(Request $request, Exam $exam)
    {
        $periode = $this->currentPeriode();

        $entry = $exam->practiceLeaderboards()
            ->where('periode', $periode)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $entry) {
            return response()->json([
                'message' => 'Kamu belum punya ranking di exam ini untuk periode berjalan.',
            ], 404);
        }

        $totalPeserta = $exam->practiceLeaderboards()
            ->where('periode', $periode)
            ->count();

        return response()->json([
            'periode' => $periode,
            'ranking' => $entry->ranking,
            'total_peserta' => $totalPeserta,
            'skor_terbaik' => $entry->skor_terbaik,
            'reward_type' => $entry->reward_type,
            'discount_code' => $entry->discount_code,
            'summary_text' => "Rank {$entry->ranking} dari {$totalPeserta} peserta minggu ini",
        ]);
    }

    /**
     * GET /api/exams/leaderboard/ranked
     * Daftar exam latihan soal yang punya leaderboard mingguan aktif
     * (minimal 1 entri) di periode berjalan. Dipakai frontend untuk isi
     * dropdown pemilihan exam, versi practice dari examBatchService.listRanked().
     */
    public function ranked(Request $request)
    {
        $periode = $this->currentPeriode();

        $examIds = \App\Models\PracticeLeaderboard::where('periode', $periode)
            ->distinct()
            ->pluck('exam_id');

        $exams = Exam::whereIn('id', $examIds)
            ->withCount(['practiceLeaderboards as participants_count' => function ($q) use ($periode) {
                $q->where('periode', $periode);
            }])
            ->get(['id', 'title']);

        return response()->json([
            'periode' => $periode,
            'data' => $exams,
        ]);
    }

    /**
     * Format periode ISO week untuk "sekarang", konsisten dengan
     * PracticeLeaderboardService::formatPeriode().
     */
    protected function currentPeriode(): string
    {
        $now = Carbon::now();

        return $now->format('o') . '-W' . str_pad((string) $now->isoWeek(), 2, '0', STR_PAD_LEFT);
    }
}

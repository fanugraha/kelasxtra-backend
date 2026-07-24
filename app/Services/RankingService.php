<?php

namespace App\Services;

use App\Models\LeaderboardSnapshot;
use App\Models\User;

class RankingService
{
    /**
     * Ambil ranking user dari leaderboard snapshot batch TERAKHIR yang dia
     * ikuti dalam sebuah program. Sengaja per-batch (bukan agregat lintas
     * batch dalam program) -- lihat catatan produk: leaderboard per-event
     * lebih adil & actionable daripada agregat program yang basis
     * pesertanya bisa timpang antar program.
     */
    public function latestRanking(User $user, int $programId): ?array
    {
        $snapshot = LeaderboardSnapshot::where('user_id', $user->id)
            ->whereHas('examBatch.exam', fn ($q) => $q->where('program_id', $programId))
            ->with('examBatch')
            ->orderByDesc('generated_at')
            ->first();

        if (! $snapshot) {
            return null;
        }

        $totalParticipants = LeaderboardSnapshot::where('exam_batch_id', $snapshot->exam_batch_id)->count();

        return [
            'rank' => $snapshot->rank,
            'total_participants' => $totalParticipants,
            'percentile' => (float) $snapshot->percentile,
            'exam_batch_id' => $snapshot->exam_batch_id,
            'exam_batch_name' => $snapshot->examBatch?->name,
            'generated_at' => $snapshot->generated_at?->toDateTimeString(),
        ];
    }
}

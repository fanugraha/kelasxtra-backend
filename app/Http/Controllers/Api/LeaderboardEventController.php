<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LeaderboardEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class LeaderboardEventController extends Controller
{
    /**
     * GET /api/leaderboard-events/me
     * Event rank-change milik user yang login, di periode aktif. Dipakai
     * untuk notifikasi personal -- dipanggil sekali saat load Beranda, dan
     * bisa juga dipanggil ulang dengan ?since= buat cek event baru.
     */
    public function me(Request $request)
    {
        $periode = $this->currentPeriode();
        $since = $request->filled('since') ? Carbon::parse($request->since) : now()->subMinutes(10);

        $events = LeaderboardEvent::with('exam:id,title')
            ->where('user_id', $request->user()->id)
            ->where('periode', $periode)
            ->where('created_at', '>=', $since)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn ($event) => [
                'id' => $event->id,
                'exam_title' => $event->exam->title,
                'old_rank' => $event->old_rank,
                'new_rank' => $event->new_rank,
                'is_milestone' => $event->is_milestone,
                'created_at' => $event->created_at,
            ]);

        return response()->json(['events' => $events]);
    }

    /**
     * GET /api/leaderboard-events/feed
     * Event rank-change milik siswa LAIN, di periode aktif -- untuk feed
     * publik. Exclude user yang login sendiri (dia dapat notif personal
     * lewat endpoint /me, jangan dobel) dan exclude yang mengaktifkan
     * hide_from_leaderboard_feed. Nama dipotong jadi "Nama I." di sini,
     * bukan di frontend, supaya nama lengkap memang tidak pernah dikirim.
     */
    public function feed(Request $request)
    {
        $periode = $this->currentPeriode();
        $since = $request->filled('since') ? Carbon::parse($request->since) : now()->subMinutes(2);

        $events = LeaderboardEvent::with('user:id,name,hide_from_leaderboard_feed')
            ->where('periode', $periode)
            ->where('user_id', '!=', $request->user()->id)
            ->where('created_at', '>=', $since)
            ->whereHas('user', fn ($q) => $q->where('hide_from_leaderboard_feed', false))
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn ($event) => [
                'id' => $event->id,
                'display_name' => $this->truncateName($event->user->name),
                'new_rank' => $event->new_rank,
                'is_milestone' => $event->is_milestone,
                'created_at' => $event->created_at,
            ]);

        return response()->json(['events' => $events]);
    }

    /**
     * Format "Budi Santoso" -> "Budi S." -- nama depan utuh, sisanya inisial.
     */
    protected function truncateName(string $name): string
    {
        $parts = explode(' ', trim($name));
        if (count($parts) === 1) {
            return $parts[0];
        }
        $first = $parts[0];
        $lastInitial = mb_substr(end($parts), 0, 1);
        return "{$first} {$lastInitial}.";
    }

    /**
     * Periode ISO week aktif sekarang, format sama dengan yang dipakai
     * PracticeLeaderboardService ("2026-W29").
     */
    protected function currentPeriode(): string
    {
        $now = now();
        return $now->format('o') . '-W' . str_pad((string) $now->isoWeek(), 2, '0', STR_PAD_LEFT);
    }
}

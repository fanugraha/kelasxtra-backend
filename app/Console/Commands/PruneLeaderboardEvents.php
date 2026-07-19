<?php

namespace App\Console\Commands;

use App\Models\LeaderboardEvent;
use Illuminate\Console\Command;

class PruneLeaderboardEvents extends Command
{
    protected $signature = 'leaderboard:prune-events {--days=30 : Hapus event lebih tua dari N hari}';

    protected $description = 'Hapus leaderboard_events yang lebih lama dari N hari (default 30) supaya tabel tidak menggembung.';

    public function handle(): int
    {
        $days = (int) $this->option('days');

        $deleted = LeaderboardEvent::where('created_at', '<', now()->subDays($days))->delete();

        $this->info("Terhapus {$deleted} leaderboard_events yang lebih tua dari {$days} hari.");

        return self::SUCCESS;
    }
}

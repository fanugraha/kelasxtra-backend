<?php

namespace App\Jobs;

use App\Models\ExamBatch;
use App\Services\LeaderboardService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateLeaderboardJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected ExamBatch $examBatch)
    {
    }

    public function handle(LeaderboardService $leaderboardService): void
    {
        $leaderboardService->generateForBatch($this->examBatch);
    }
}
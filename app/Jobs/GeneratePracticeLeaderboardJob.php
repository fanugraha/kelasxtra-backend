<?php

namespace App\Jobs;

use App\Models\Exam;
use App\Services\PracticeLeaderboardService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GeneratePracticeLeaderboardJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected Exam $exam)
    {
    }

    public function handle(PracticeLeaderboardService $service): void
    {
        $service->generateForExam($this->exam);
    }
}

<?php

namespace App\Jobs;

use App\Models\ExamAttempt;
use App\Services\TopicMasteryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Async supaya request submit ujian siswa (momen paling sensitif) tidak
 * ikut menanggung beban hitung rollup -- dashboard orang tua/rekomendasi
 * yang membaca hasilnya toleran terhadap delay singkat, siswa yang baru
 * submit ujian tidak.
 */
class GenerateTopicMasterySnapshotJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected ExamAttempt $attempt)
    {
    }

    public function handle(TopicMasteryService $service): void
    {
        $service->refreshForAttempt($this->attempt);
    }
}

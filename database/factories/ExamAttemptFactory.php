<?php

namespace Database\Factories;

use App\Models\ExamAttempt;
use App\Models\User;
use App\Models\Exam;
use App\Models\ExamBatch;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class ExamAttemptFactory extends Factory
{
    protected $model = ExamAttempt::class;

    public function definition(): array
    {
        $startedAt = Carbon::now()->subHours(rand(1, 5));
        // Durasi pengerjaan acak antara 15 sampai 30 menit
        $finishedAt = (clone $startedAt)->addMinutes(rand(15, 30)); 

        return [
            'user_id' => User::factory(), // Akan dioverride di seeder
            'exam_id' => null,            // Akan dioverride di seeder
            'exam_batch_id' => null,      // Akan dioverride di seeder
            'score' => rand(10, 100),     // Skor acak untuk simulasi ranking
            'correct_count' => rand(5, 20),
            'started_at' => $startedAt,
            'finished_at' => $finishedAt,
            'status' => 'graded',         // Tandai siap di-snapshot
            'question_order' => ['questions' => [78, 77], 'options' => []],
            'tab_switch_count' => rand(0, 3),
        ];
    }
}
<?php

namespace Database\Factories;

use App\Models\ExamBatch;
use App\Models\LeaderboardSnapshot;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeaderboardSnapshotFactory extends Factory
{
    protected $model = LeaderboardSnapshot::class;

    public function definition(): array
    {
        return [
            'exam_batch_id' => ExamBatch::factory(),
            'user_id' => User::factory(),
            'score' => $this->faker->numberBetween(50, 100),
            'rank' => $this->faker->numberBetween(1, 100),
            'percentile' => $this->faker->randomFloat(2, 0, 100),
            'correct_count' => $this->faker->numberBetween(10, 100),
            'duration_seconds' => $this->faker->numberBetween(600, 5400),
            'generated_at' => now(),
        ];
    }
}

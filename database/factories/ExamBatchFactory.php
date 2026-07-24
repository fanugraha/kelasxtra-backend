<?php

namespace Database\Factories;

use App\Models\Exam;
use App\Models\ExamBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExamBatchFactory extends Factory
{
    protected $model = ExamBatch::class;

    public function definition(): array
    {
        return [
            'exam_id' => Exam::factory(),
            'name' => 'Batch '.$this->faker->numberBetween(1, 100),
            'start_at' => now()->subDays(7),
            'end_at' => now()->subDays(6),
            'is_national' => false,
            'status' => 'finished',
        ];
    }
}

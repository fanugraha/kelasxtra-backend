<?php

namespace Database\Factories;

use App\Models\Question;
use App\Models\QuestionBank;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuestionFactory extends Factory
{
    protected $model = Question::class;

    public function definition(): array
    {
        return [
            'bank_id' => QuestionBank::factory(),
            'question_text' => $this->faker->sentence(10) . '?',
            'media_url' => null,
            'media_type' => 'none',
            'passage_id' => null,
            'type' => 'pg',
            'difficulty' => 'sedang',
            'explanation' => null,
            'point_correct_override' => null,
            'point_wrong_override' => null,
            'topic_id' => null,
        ];
    }

    public function essay(): static
    {
        return $this->state(fn () => ['type' => 'essay']);
    }
}

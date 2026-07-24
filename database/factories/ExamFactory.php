<?php

namespace Database\Factories;

use App\Models\Exam;
use App\Models\Program;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExamFactory extends Factory
{
    protected $model = Exam::class;

    public function definition(): array
    {
        return [
            'program_id' => Program::factory(),
            'focus_mode' => 'all_program',
            'focus_taxonomy_id' => null,
            'title' => $this->faker->sentence(4),
            'duration_minutes' => 60,
            'passing_score' => null,
            'require_all_sections_pass' => false,
            'uses_section_timers' => false,
            'is_free_preview' => false,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Program;
use App\Models\QuestionBank;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * taxonomy_id TIDAK punya default -- QuestionBank::booted() menolak save
 * kalau program_id terisi tapi taxonomy_id kosong. Taxonomy sendiri tidak
 * pakai HasFactory, jadi caller WAJIB buat Taxonomy manual (Taxonomy::create())
 * dan override taxonomy_id + program_id di sini. Lihat helper
 * makeExamWithSection() di ExamScoringServiceTest.
 */
class QuestionBankFactory extends Factory
{
    protected $model = QuestionBank::class;

    public function definition(): array
    {
        return [
            'program_id' => Program::factory(),
            'taxonomy_id' => null,
            'title' => $this->faker->sentence(3),
            'scoring_type' => 'single_correct',
            'point_correct' => 5,
            'point_wrong' => 0,
        ];
    }
}

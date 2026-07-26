<?php

namespace Database\Factories;

use App\Models\Package;
use App\Models\Program;
use Illuminate\Database\Eloquent\Factories\Factory;

class PackageFactory extends Factory
{
    protected $model = Package::class;

    public function definition(): array
    {
        return [
            'program_id' => Program::factory(),
            'taxonomy_id' => null,
            'name' => $this->faker->words(3, true),
            'type' => 'latihan_soal',
            'price' => $this->faker->randomFloat(2, 50000, 500000),
            'discount_price' => null,
            'duration_days' => 30,
            'description' => $this->faker->sentence(),
            'features' => [],
            'materi' => [],
            'banner_image_url' => null,
            'is_focus_topic' => false,
            'focus_taxonomy_id' => null,
        ];
    }
}

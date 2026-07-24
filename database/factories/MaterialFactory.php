<?php

namespace Database\Factories;

use App\Models\ClassRoom;
use App\Models\Material;
use Illuminate\Database\Eloquent\Factories\Factory;

class MaterialFactory extends Factory
{
    protected $model = Material::class;

    public function definition(): array
    {
        return [
            'class_id' => ClassRoom::factory(),
            'title' => $this->faker->sentence(3),
            'file_url' => $this->faker->url(),
            'type' => 'pdf',
        ];
    }
}

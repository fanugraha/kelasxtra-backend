<?php

namespace Database\Factories;

use App\Models\ClassRoom;
use App\Models\Package;
use App\Models\Tutor;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClassRoomFactory extends Factory
{
    protected $model = ClassRoom::class;

    public function definition(): array
    {
        return [
            'package_id' => Package::factory(),
            'tutor_id' => Tutor::factory(),
            'name' => $this->faker->words(2, true),
            'capacity' => 20,
            'status' => 'active',
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Enrollment;
use App\Models\Package;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EnrollmentFactory extends Factory
{
    protected $model = Enrollment::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'class_id' => null,
            'package_id' => Package::factory(),
            'transaction_id' => null,
            'status' => 'active',
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonth(),
        ];
    }
}

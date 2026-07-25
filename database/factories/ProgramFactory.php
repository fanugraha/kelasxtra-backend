<?php

namespace Database\Factories;

use App\Models\Program;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProgramFactory extends Factory
{
    protected $model = Program::class;

    // Nama program dari daftar realistis, bukan kata acak Faker --
    // supaya UI (dropdown kategori, card promo, badge plan langganan) tidak
    // pernah menampilkan teks seperti "repudiandae sunt temporibus".
    protected static array $realisticNames = [
        'SKD CPNS',
        'Sekolah Kedinasan',
        'PPPK Guru',
        'PPPK Non-Guru',
        'TOEFL Preparation',
        'Tes Potensi Akademik',
        'Psikotes Kerja',
        'UTBK SNBT',
    ];

    public function definition(): array
    {
        $name = $this->faker->unique()->randomElement(static::$realisticNames);

        return [
            'brand_id' => null,
            'name' => $name,
            'slug' => \Illuminate\Support\Str::slug($name).'-'.$this->faker->unique()->numberBetween(1000, 9999),
            'description' => $this->faker->sentence(),
            'icon' => null,
            'is_active' => true,
            'question_grouping_mode' => 'category',
        ];
    }
}

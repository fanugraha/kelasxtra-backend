<?php

namespace Database\Factories;

use App\Models\Program;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionPlanFactory extends Factory
{
    protected static array $featurePool = [
        'Akses semua try out di program ini',
        'Latihan soal tanpa batas',
        'Analisis kelemahan per topik',
        'Leaderboard mingguan',
        'Pembahasan lengkap tiap soal',
        'Export laporan skor ke Excel',
        'Akses kelas online reguler',
        'Prioritas dukungan tanya CS',
    ];

    public function definition(): array
    {
        return [
            'name' => 'Langganan Bulanan',
            'tagline' => null,
            'description' => 'Akses penuh ke satu program selama masa aktif langganan.',
            'features' => $this->faker->randomElements(static::$featurePool, 4),
            'duration_days' => 30,
            'program_slot_count' => null,
            'program_id' => Program::factory(),
            'price' => 150000,
            'is_active' => true,
            'is_featured' => false,
        ];
    }

    // Plan multi-select: tidak fix ke satu program, butuh N program dipilih saat checkout.
    public function multiProgram(int $slots = 2): static
    {
        return $this->state(fn () => [
            'name' => "Langganan {$slots} Program",
            'tagline' => 'Semua Program',
            'description' => "Pilih bebas {$slots} program sekaligus, lebih hemat dibanding langganan satuan.",
            'program_id' => null,
            'program_slot_count' => $slots,
            'price' => 150000 * $slots * 0.8, // diskon paket vs beli satuan
        ]);
    }

    public function featured(): static
    {
        return $this->state(fn () => [
            'tagline' => 'Paling Populer',
            'is_featured' => true,
        ]);
    }
}

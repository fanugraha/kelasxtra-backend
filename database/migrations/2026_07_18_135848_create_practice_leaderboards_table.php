<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('practice_leaderboards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Format periode: "2026-W29" (tahun-minggu ISO), dipakai buat reset mingguan
            $table->string('periode', 10);

            $table->unsignedInteger('skor_terbaik');
            $table->unsignedInteger('ranking');

            // badge_only = menang tapi tidak dapat voucher (syarat reward tidak lolos)
            // voucher_gold/silver/bronze = menang & dapat voucher (rank 1/2/3)
            $table->enum('reward_type', ['badge_only', 'voucher_gold', 'voucher_silver', 'voucher_bronze'])
                ->nullable();

            // Kode promo yang di-generate (reference ke promos.code), null kalau reward_type = badge_only
            $table->string('discount_code')->nullable();

            $table->timestamp('reward_claimed_at')->nullable();

            $table->timestamps();

            // 1 siswa cuma 1 entri per exam per periode (skor terbaiknya)
            $table->unique(['exam_id', 'user_id', 'periode']);

            // Dipakai buat query ranking per exam per minggu
            $table->index(['exam_id', 'periode', 'ranking']);

            // Dipakai buat cek jatah voucher user per minggu (Tahap 5 nanti)
            $table->index(['user_id', 'periode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('practice_leaderboards');
    }
};

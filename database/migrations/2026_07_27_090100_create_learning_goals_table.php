<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // P3 scaffolding (27 Jul 2026): tabel target belajar ("learning goals").
    //
    // Keputusan produk dari diskusi: fitur ini HANYA relevan untuk program
    // dengan question_grouping_mode = 'subject' (brand Sekolah/SNBT-UTBK).
    // Program CPNS/BUMN (question_grouping_mode = 'category') sudah punya
    // passing grade nasional yang fixed dan sama untuk semua orang, jadi
    // tidak butuh "target skor personal" ala rasionalisasi SNBT.
    //
    // Guard ini SENGAJA tidak dipaksakan lewat CHECK constraint di database
    // (MySQL tidak enforce CHECK dengan baik + gampang berubah), tapi lewat
    // application layer (service/controller) yang menolak membuat/menampilkan
    // learning goal untuk program mode 'category'. Kalau nanti CPNS ternyata
    // butuh juga (misal target skor TIU custom di atas passing grade),
    // tinggal ubah logic guard-nya, tidak perlu migrasi ulang skema.
    public function up(): void
    {
        Schema::create('learning_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();

            // Null = pakai default sistem (misal skor rasionalisasi standar).
            // Diisi kalau user/orang tua override manual.
            $table->unsignedInteger('target_score')->nullable();

            $table->enum('source', ['system_default', 'user_override'])
                ->default('system_default');

            $table->timestamps();

            $table->unique(['user_id', 'program_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_goals');
    }
};

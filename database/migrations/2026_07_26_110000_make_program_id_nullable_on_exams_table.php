<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // CATATAN (26 Jul 2026): Topic mode subject (taxonomy type=subject)
    // sengaja punya program_id null -- mapel bersifat global lintas
    // Program. Part 1 dari topic seperti ini tetap harus bisa di-generate
    // (selalu free preview), jadi kolom exams.program_id tidak boleh lagi
    // NOT NULL. Foreign key + cascadeOnDelete tetap dipertahankan; kolom
    // yang nullable + FK null otomatis diabaikan constraint-nya oleh MySQL.
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropForeign(['program_id']);
        });

        Schema::table('exams', function (Blueprint $table) {
            $table->foreignId('program_id')->nullable()->change();
        });

        Schema::table('exams', function (Blueprint $table) {
            $table->foreign('program_id')->references('id')->on('programs')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropForeign(['program_id']);
        });

        Schema::table('exams', function (Blueprint $table) {
            $table->foreignId('program_id')->nullable(false)->change();
        });

        Schema::table('exams', function (Blueprint $table) {
            $table->foreign('program_id')->references('id')->on('programs')->cascadeOnDelete();
        });
    }
};

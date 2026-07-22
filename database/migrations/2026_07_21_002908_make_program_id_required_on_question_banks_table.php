<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sebelumnya dibuat nullable (migration 2026_07_11_120139) untuk
        // kasus Bank Soal lepas tanpa Program. Sekarang dipastikan semua
        // Bank Soal WAJIB punya Program (sudah dicek: 0 baris NULL saat ini),
        // jadi dikembalikan jadi NOT NULL supaya konsisten dengan form.
        Schema::table('question_banks', function (Blueprint $table) {
            $table->foreignId('program_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('question_banks', function (Blueprint $table) {
            $table->foreignId('program_id')->nullable()->change();
        });
    }
};

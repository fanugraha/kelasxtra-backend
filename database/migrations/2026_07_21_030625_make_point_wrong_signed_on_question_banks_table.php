<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // point_wrong dulu unsignedInteger -- padahal secara bisnis admin
        // perlu bisa isi skor minus untuk jawaban salah (mis. skema TWK/TIU:
        // benar +5, salah -1, kosong 0). Ganti jadi integer biasa (signed)
        // supaya nilai negatif diterima DB, bukan ditolak dengan error 500
        // begitu form-nya lolos validasi Filament.
        Schema::table('question_banks', function (Blueprint $table) {
            $table->integer('point_wrong')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('question_banks', function (Blueprint $table) {
            $table->unsignedInteger('point_wrong')->nullable()->change();
        });
    }
};

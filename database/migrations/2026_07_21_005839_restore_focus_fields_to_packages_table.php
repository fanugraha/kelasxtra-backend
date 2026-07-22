<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Mengembalikan kolom yang sempat ke-drop di migration
        // 2026_07_20_111048 -- fitur "Paket Fokus 1 Topik" masih dipakai:
        // admin perlu bisa pilih apakah Paket ini fokus jual 1 topik
        // (Kategori untuk brand CPNS/BUMN, atau Mapel untuk brand
        // Sekolah/Masuk Kuliah lewat focus_subject_id) atau general
        // (gabungan semua topik).
        Schema::table('packages', function (Blueprint $table) {
            $table->boolean('is_focus_topic')->default(false);
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
            $table->dropColumn('is_focus_topic');
        });
    }
};

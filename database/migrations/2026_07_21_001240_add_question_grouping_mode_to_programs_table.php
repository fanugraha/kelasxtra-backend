<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            // Menandai pola pengelompokan soal yang dipakai Program ini:
            // 'category' -> brand CPNS/BUMN/Kedinasan (TWK/TIU/TKP, banyak
            //   bagian sekaligus dalam satu ujian).
            // 'subject'  -> brand Sekolah/Masuk Kuliah (Matematika/Fisika,
            //   latihan soal satu per mapel, tidak digabung jadi paket ujian).
            // Default 'category' supaya semua Program yang sudah ada (dibuat
            // sebelum kolom ini ada) tetap berperilaku sama seperti sekarang,
            // tidak ada migrasi data manual yang diperlukan.
            $table->enum('question_grouping_mode', ['category', 'subject'])
                ->default('category')
                ->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->dropColumn('question_grouping_mode');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Paralel dengan category_id (Topik Fokus versi brand Kategori).
        // Dipakai saat Paket "Fokus 1 Topik" di dalam Program bermode Mapel
        // (mis. paket "Latihan Fokus Matematika SNBT"). BEDA dari kolom
        // subject_id yang sudah ada -- itu dipakai untuk paket bimbel per-
        // mapel yang berdiri sendiri TANPA Program sama sekali.
        Schema::table('packages', function (Blueprint $table) {
            $table->foreignId('focus_subject_id')->nullable()
                ->constrained('subjects')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('focus_subject_id');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 'reguler' dihapus dari enum type paket. Alasan: Packages.jsx (katalog
 * siswa) tidak pernah punya tab/filter untuk tipe ini, jadi paket bertipe
 * 'reguler' tidak akan pernah bisa ditemukan/dibeli siswa lewat jalur
 * normal. Sudah dicek ke production sebelum migrasi ini dibuat -- belum
 * ada satupun baris packages dengan type='reguler', jadi aman dihapus
 * tanpa perlu migrasi data.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Jaga-jaga: gagalkan migrasi dengan jelas kalau ternyata ada baris
        // yang lolos dari pengecekan manual sebelumnya, alih-alih diam-diam
        // membuat baris itu punya nilai enum yang tidak valid.
        $stillUsed = DB::table('packages')->where('type', 'reguler')->count();

        if ($stillUsed > 0) {
            throw new \RuntimeException(
                "Migrasi dibatalkan: masih ada {$stillUsed} baris packages dengan type='reguler'. ".
                'Migrasikan/hapus data itu dulu sebelum menjalankan migrasi ini.'
            );
        }

        DB::statement("ALTER TABLE packages MODIFY COLUMN type ENUM('privat', 'group', 'latihan_soal') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE packages MODIFY COLUMN type ENUM('privat', 'group', 'latihan_soal', 'reguler') NOT NULL");
    }
};

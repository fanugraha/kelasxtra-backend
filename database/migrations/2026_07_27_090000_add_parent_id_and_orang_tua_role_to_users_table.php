<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // P3 scaffolding (27 Jul 2026): menyiapkan relasi orang tua-anak untuk
    // fitur dashboard multi-anak yang belum dikerjakan sekarang. Keputusan
    // produk: 1 orang tua bisa pantau banyak anak, 1 anak cukup 1 orang tua
    // (self-referential FK biasa, bukan tabel pivot).
    //
    // Enum role ditambah 'orang_tua' supaya nanti orang tua bisa login lewat
    // akun sendiri (bukan sekadar viewer mode di akun anak). Belum ada UI/
    // endpoint yang memanfaatkan role ini -- disiapkan dulu agar migrasi
    // berikutnya tidak perlu ubah skema lagi.
    public function up(): void
    {
        // MySQL enum tidak bisa diubah lewat Blueprint::change() secara aman
        // (doctrine/dbal cenderung menjatuhkan tipe enum jadi varchar), jadi
        // pakai raw ALTER TABLE MODIFY COLUMN seperti kebiasaan Laravel+MySQL.
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'tutor', 'siswa', 'orang_tua') NOT NULL DEFAULT 'siswa'");

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('parent_id')
                ->nullable()
                ->after('id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
        });

        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'tutor', 'siswa') NOT NULL DEFAULT 'siswa'");
    }
};

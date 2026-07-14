<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // No-op: kolom excerpt, program_id, dan foreign key-nya
        // sudah dibuat langsung di 2025_01_01_000020_create_articles_table.php.
        // Migration ini dibiarkan ada (tidak dihapus) karena sudah tercatat
        // sebagai "ran" di tabel migrations pada server production.
    }

    public function down(): void
    {
        // No-op juga, sesuai isi up() di atas.
    }
};

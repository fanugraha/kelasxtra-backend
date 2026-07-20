<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Asumsi MySQL. Kalau Postgres, ganti pakai pendekatan check constraint.
        DB::statement("ALTER TABLE transactions MODIFY status ENUM('pending','success','failed','expired','refunded') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE transactions MODIFY status ENUM('pending','success','failed','expired') NOT NULL DEFAULT 'pending'");
    }
};

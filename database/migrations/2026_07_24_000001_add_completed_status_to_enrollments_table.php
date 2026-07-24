<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE enrollments MODIFY COLUMN status ENUM('pending','active','expired','completed') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE enrollments MODIFY COLUMN status ENUM('pending','active','expired') NOT NULL DEFAULT 'pending'");
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            // Dipakai oleh AccessControlService::hasFullPerformanceAccess()
            // untuk cek cepat enrollment aktif/selesai milik user.
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'status']);
        });
    }
};

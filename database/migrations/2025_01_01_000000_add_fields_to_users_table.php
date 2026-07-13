<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
            $table->enum('role', ['admin', 'tutor', 'siswa'])->default('siswa')->after('password');
            $table->enum('level_pendidikan', ['sd', 'smp', 'sma', 'mahasiswa', 'umum'])
                  ->nullable()->after('role');
            $table->boolean('is_active')->default(true)->after('level_pendidikan');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'role', 'level_pendidikan', 'is_active']);
        });
    }
};

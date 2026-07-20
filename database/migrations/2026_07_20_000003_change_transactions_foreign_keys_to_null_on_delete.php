<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Catatan: butuh package `doctrine/dbal` terpasang untuk ->change().
// Kalau belum ada: composer require doctrine/dbal

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['package_id']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->unsignedBigInteger('package_id')->nullable()->change();

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('package_id')->references('id')->on('packages')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['package_id']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->unsignedBigInteger('package_id')->nullable(false)->change();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('package_id')->references('id')->on('packages')->cascadeOnDelete();
        });
    }
};

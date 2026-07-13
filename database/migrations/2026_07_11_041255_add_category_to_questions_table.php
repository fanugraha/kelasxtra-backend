<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            // twk, tiu, tkp, reading, structure, listening, dst — nullable karena
            // exam lama (matematika testing) tidak butuh kategori
            $table->string('category')->nullable()->after('type');
        });
    }

    public function rollback(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
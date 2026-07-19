<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promos', function (Blueprint $table) {
            $table->date('valid_until')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('promos', function (Blueprint $table) {
            $table->date('valid_until')->nullable(false)->change();
        });
    }
};
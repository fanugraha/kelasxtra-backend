<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->integer('point_correct_override')->nullable()->after('difficulty');
            $table->integer('point_wrong_override')->nullable()->after('point_correct_override');
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn(['point_correct_override', 'point_wrong_override']);
        });
    }
};

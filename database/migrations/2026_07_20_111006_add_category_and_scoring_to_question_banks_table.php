<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('question_banks', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('program_id')
                ->constrained()->nullOnDelete();
            $table->enum('scoring_type', ['single_correct', 'weighted_options'])->nullable()->after('category_id');
            $table->unsignedInteger('point_correct')->nullable()->after('scoring_type');
            $table->unsignedInteger('point_wrong')->nullable()->after('point_correct');
        });
    }

    public function down(): void
    {
        Schema::table('question_banks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
            $table->dropColumn(['scoring_type', 'point_correct', 'point_wrong']);
        });
    }
};

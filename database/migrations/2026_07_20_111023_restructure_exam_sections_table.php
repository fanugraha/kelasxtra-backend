<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_sections', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });

        Schema::table('exam_sections', function (Blueprint $table) {
            $table->foreignId('category_id')->after('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_bank_id')->after('category_id')->constrained()->cascadeOnDelete();
            $table->dropColumn('points_per_question');
        });
    }

    public function down(): void
    {
        Schema::table('exam_sections', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
            $table->dropConstrainedForeignId('question_bank_id');
            $table->unsignedInteger('points_per_question')->nullable();
        });

        Schema::table('exam_sections', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('exam_id')->constrained()->nullOnDelete();
        });
    }
};

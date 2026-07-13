<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->boolean('require_all_sections_pass')->default(false)->after('passing_score');
        });

        Schema::table('exam_questions', function (Blueprint $table) {
            $table->foreignId('exam_section_id')->nullable()->after('exam_id')->constrained();
        });

        Schema::table('question_options', function (Blueprint $table) {
            $table->unsignedInteger('points')->default(0)->after('option_text');
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropColumn('require_all_sections_pass');
        });

        Schema::table('exam_questions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('exam_section_id');
        });

        Schema::table('question_options', function (Blueprint $table) {
            $table->dropColumn('points');
        });
    }
};
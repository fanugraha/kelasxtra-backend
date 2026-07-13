<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_questions', function (Blueprint $table) {
            $table->index('exam_id', 'exam_questions_exam_id_tmp_idx');
            $table->index('question_id', 'exam_questions_question_id_index');
        });

        Schema::table('exam_questions', function (Blueprint $table) {
            $table->dropPrimary(['exam_id', 'question_id']);
        });

        Schema::table('exam_questions', function (Blueprint $table) {
            $table->id()->first();
            $table->unique(['exam_id', 'question_id'], 'exam_questions_exam_id_question_id_unique');
        });

        Schema::table('exam_questions', function (Blueprint $table) {
            $table->dropIndex('exam_questions_exam_id_tmp_idx');
        });
    }

    public function down(): void
    {
        Schema::table('exam_questions', function (Blueprint $table) {
            $table->index('exam_id', 'exam_questions_exam_id_tmp_idx');
        });

        Schema::table('exam_questions', function (Blueprint $table) {
            $table->dropUnique('exam_questions_exam_id_question_id_unique');
            $table->dropColumn('id');
        });

        Schema::table('exam_questions', function (Blueprint $table) {
            $table->primary(['exam_id', 'question_id']);
        });

        Schema::table('exam_questions', function (Blueprint $table) {
            $table->dropIndex('exam_questions_exam_id_tmp_idx');
            $table->dropIndex('exam_questions_question_id_index');
        });
    }
};

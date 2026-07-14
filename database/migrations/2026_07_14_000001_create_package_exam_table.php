<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('package_question_bank');

        Schema::create('package_exam', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['package_id', 'exam_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_exam');

        Schema::create('package_question_bank', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_bank_id')->constrained('question_banks')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['package_id', 'question_bank_id']);
        });
    }
};

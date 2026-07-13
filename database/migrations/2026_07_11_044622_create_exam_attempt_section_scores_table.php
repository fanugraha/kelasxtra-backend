<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_attempt_section_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_section_id')->constrained();
            $table->unsignedInteger('raw_score')->default(0);
            $table->unsignedInteger('correct_count')->default(0);
            $table->boolean('passed_threshold')->nullable();
            $table->timestamps();

            $table->unique(['exam_attempt_id', 'exam_section_id'], 'eass_attempt_section_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_attempt_section_scores');
    }
};
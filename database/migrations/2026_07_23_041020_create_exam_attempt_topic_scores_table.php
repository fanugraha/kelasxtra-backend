<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_attempt_topic_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_attempt_id')->constrained('exam_attempts')->cascadeOnDelete();
            $table->foreignId('topic_id')->constrained('topics')->cascadeOnDelete();
            $table->unsignedInteger('correct_count')->default(0);
            $table->unsignedInteger('total_count')->default(0);
            $table->integer('raw_score')->nullable();
            $table->timestamps();

            $table->unique(['exam_attempt_id', 'topic_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_attempt_topic_scores');
    }
};

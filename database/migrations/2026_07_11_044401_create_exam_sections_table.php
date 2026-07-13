<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->unsignedInteger('order')->default(0);
            $table->enum('scoring_type', ['single_correct', 'weighted_options']);
            $table->unsignedInteger('points_per_question')->nullable();
            $table->unsignedInteger('min_passing_score')->nullable();
            $table->unsignedInteger('max_score')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->boolean('is_locked_after_next')->default(false);
            $table->timestamps();

            $table->unique(['exam_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_sections');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
            $table->foreignId('exam_batch_id')->nullable()->constrained('exam_batches')->nullOnDelete();
            $table->unsignedInteger('score')->nullable();
            $table->unsignedInteger('correct_count')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->enum('status', ['in_progress', 'submitted', 'auto_submitted', 'graded'])
                  ->default('in_progress');
            $table->json('question_order')->nullable(); // urutan soal & opsi hasil randomisasi
            $table->unsignedInteger('tab_switch_count')->default(0);
            $table->timestamps();

            $table->index(['exam_batch_id', 'score']); // dipakai saat generate ranking
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_attempts');
    }
};

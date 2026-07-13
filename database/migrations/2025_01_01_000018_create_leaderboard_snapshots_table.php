<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leaderboard_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_batch_id')->constrained('exam_batches')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('score');
            $table->unsignedInteger('rank');
            $table->decimal('percentile', 5, 2);
            $table->unsignedInteger('correct_count');
            $table->unsignedInteger('duration_seconds');
            $table->timestamp('generated_at');
            $table->timestamps();

            $table->unique(['exam_batch_id', 'user_id']);
            $table->index(['exam_batch_id', 'rank']); // dipakai query leaderboard
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leaderboard_snapshots');
    }
};

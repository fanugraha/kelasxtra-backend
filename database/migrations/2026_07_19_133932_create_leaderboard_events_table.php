<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leaderboard_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('periode', 10);
            $table->unsignedInteger('old_rank')->nullable();
            $table->unsignedInteger('new_rank');
            $table->boolean('is_milestone')->default(false);
            $table->timestamps();

            $table->index(['periode', 'created_at']);
            $table->index(['exam_id', 'user_id', 'periode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leaderboard_events');
    }
};

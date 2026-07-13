<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
            $table->string('name');
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->boolean('is_national')->default(false);
            $table->enum('status', ['scheduled', 'ongoing', 'finished', 'ranked'])->default('scheduled');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_batches');
    }
};

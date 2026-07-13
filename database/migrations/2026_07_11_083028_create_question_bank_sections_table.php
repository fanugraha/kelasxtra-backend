<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   public function up(): void
{
    Schema::create('question_bank_sections', function (Blueprint $table) {
        $table->id();
        $table->foreignId('question_bank_id')->constrained()->cascadeOnDelete();
        $table->foreignId('category_id')->constrained()->cascadeOnDelete();
        $table->unsignedInteger('target_count');
        $table->timestamps();

        $table->unique(['question_bank_id', 'category_id']);
    });
}

public function down(): void
{
    Schema::dropIfExists('question_bank_sections');
}
};

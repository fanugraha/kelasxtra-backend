<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   public function up(): void
{
    Schema::create('question_passages', function (Blueprint $table) {
        $table->id();
        $table->foreignId('question_bank_id')->constrained()->cascadeOnDelete();
        $table->text('passage_text')->nullable();
        $table->string('media_url')->nullable();
        $table->enum('media_type', ['image', 'audio', 'none'])->default('none');
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('question_passages');
}
};

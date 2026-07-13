<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   public function up(): void
{
    Schema::create('categories', function (Blueprint $table) {
        $table->id();
        $table->foreignId('program_id')->constrained()->cascadeOnDelete();
        $table->string('code');       // twk, tiu, tkp, reading, dst
        $table->string('name');       // "Tes Wawasan Kebangsaan"
        $table->unsignedInteger('passing_grade')->nullable();
        $table->boolean('requires_passage')->default(false);
        $table->timestamps();

        $table->unique(['program_id', 'code']);
    });
}

public function down(): void
{
    Schema::dropIfExists('categories');
}
};

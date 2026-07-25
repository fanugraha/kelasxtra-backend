<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_id')->constrained();
            $table->unique(['subscription_id', 'program_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_programs');
    }
};

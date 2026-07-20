<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('question_bank_sections');
    }

    public function down(): void
    {
        // intentionally not recreated — superseded by category-locked question_banks
    }
};

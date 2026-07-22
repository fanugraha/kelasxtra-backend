<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('question_banks', function (Blueprint $table) {
            $table->dropForeign(['subject_id']);
            $table->dropForeign(['category_id']);
            $table->dropColumn(['subject_id', 'category_id']);

            $table->foreignId('taxonomy_id')
                ->after('program_id')
                ->constrained('taxonomies')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('question_banks', function (Blueprint $table) {
            $table->dropForeign(['taxonomy_id']);
            $table->dropColumn('taxonomy_id');

            $table->unsignedBigInteger('subject_id')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
        });
    }
};

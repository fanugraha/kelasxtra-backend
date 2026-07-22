<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropForeign(['subject_id']);
            $table->dropForeign(['category_id']);
            $table->dropForeign(['focus_subject_id']);
            $table->dropColumn(['subject_id', 'category_id', 'focus_subject_id']);

            $table->foreignId('taxonomy_id')
                ->nullable()
                ->after('program_id')
                ->constrained('taxonomies')
                ->nullOnDelete();

            $table->foreignId('focus_taxonomy_id')
                ->nullable()
                ->after('is_focus_topic')
                ->constrained('taxonomies')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropForeign(['taxonomy_id']);
            $table->dropForeign(['focus_taxonomy_id']);
            $table->dropColumn(['taxonomy_id', 'focus_taxonomy_id']);

            $table->unsignedBigInteger('subject_id')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('focus_subject_id')->nullable();
        });
    }
};

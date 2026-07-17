<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->renameColumn('image_url', 'media_url');
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->enum('media_type', ['image', 'audio', 'none'])->default('none')->after('media_url');
            $table->foreignId('passage_id')->nullable()->after('media_type')
                ->constrained('question_passages')->nullOnDelete();
            $table->dropColumn('category');
            $table->foreignId('category_id')->nullable()->after('passage_id')
                ->constrained('categories')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropForeign(['passage_id']);
            $table->dropForeign(['category_id']);
            $table->dropColumn(['media_type', 'passage_id', 'category_id']);
            $table->string('category')->nullable();
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->renameColumn('media_url', 'image_url');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_sections', function (Blueprint $table) {
            $table->dropForeign('exam_sections_category_id_foreign');
            $table->dropForeign('exam_sections_subject_id_foreign');
            $table->dropColumn(['category_id', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::table('exam_sections', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('exam_id')->constrained('categories')->nullOnDelete();
            $table->foreignId('subject_id')->nullable()->after('category_id')->constrained('subjects')->nullOnDelete();
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kolom taxonomy_id sekarang dibuat & diisi di migration
        // 2026_07_22_040016_drop_category_and_subject_id_from_exam_sections_table
        // (harus sebelum category_id/subject_id di-drop). Guard ini cuma jaga-jaga.
        if (! Schema::hasColumn('exam_sections', 'taxonomy_id')) {
            Schema::table('exam_sections', function (Blueprint $table) {
                $table->unsignedBigInteger('taxonomy_id')->nullable()->after('exam_id');
            });
        }
    }

    public function down(): void
    {
        // no-op — kolom taxonomy_id di-drop oleh down() migration 040016
    }
};

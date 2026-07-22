<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_sections', function (Blueprint $table) {
            $table->unsignedBigInteger('taxonomy_id')->nullable()->after('exam_id');
        });

        DB::statement('UPDATE exam_sections SET taxonomy_id = COALESCE(category_id, subject_id)');
    }

    public function down(): void
    {
        Schema::table('exam_sections', function (Blueprint $table) {
            $table->dropColumn('taxonomy_id');
        });
    }
};

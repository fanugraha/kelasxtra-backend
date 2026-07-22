<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_sections', function (Blueprint $table) {
            $table->unsignedBigInteger('legacy_taxonomy_id')->nullable()->after('taxonomy_id');
        });

        DB::statement('UPDATE exam_sections SET legacy_taxonomy_id = taxonomy_id');
    }

    public function down(): void
    {
        Schema::table('exam_sections', function (Blueprint $table) {
            $table->dropColumn('legacy_taxonomy_id');
        });
    }
};

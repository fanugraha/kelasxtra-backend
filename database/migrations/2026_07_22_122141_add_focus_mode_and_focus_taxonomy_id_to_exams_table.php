<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->string('focus_mode')->default('all_program')->after('program_id');
            $table->foreignId('focus_taxonomy_id')->nullable()->after('focus_mode')
                ->constrained('taxonomies')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropConstrainedForeignId('focus_taxonomy_id');
            $table->dropColumn('focus_mode');
        });
    }
};

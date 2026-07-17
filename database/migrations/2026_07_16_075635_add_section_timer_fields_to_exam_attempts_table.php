<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->foreignId('current_section_id')->nullable()->after('bank_id')
                ->constrained('exam_sections')->nullOnDelete();
            $table->timestamp('section_started_at')->nullable()->after('current_section_id');
        });
    }

    public function down(): void
    {
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_section_id');
            $table->dropColumn('section_started_at');
        });
    }
};

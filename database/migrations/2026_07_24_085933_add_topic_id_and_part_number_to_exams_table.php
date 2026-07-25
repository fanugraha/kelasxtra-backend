<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->foreignId('topic_id')
                ->nullable()
                ->after('id')
                ->constrained('topics')
                ->nullOnDelete();

            $table->unsignedInteger('part_number')->nullable()->after('topic_id');
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropConstrainedForeignId('topic_id');
            $table->dropColumn('part_number');
        });
    }
};

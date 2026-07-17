<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->foreignId('bank_id')
                ->nullable()
                ->after('exam_batch_id')
                ->constrained('question_banks')
                ->nullOnDelete();

            $table->index(['exam_id', 'bank_id', 'user_id'], 'exam_attempts_exam_bank_user_idx');
        });
    }

    public function down(): void
    {
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->dropIndex('exam_attempts_exam_bank_user_idx');
            $table->dropConstrainedForeignId('bank_id');
        });
    }
};

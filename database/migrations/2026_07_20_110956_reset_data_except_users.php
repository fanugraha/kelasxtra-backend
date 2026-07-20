<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach ([
            'transaction_logs',
            'transactions',
            'enrollments',
            'leaderboard_snapshots',
            'exam_attempt_section_scores',
            'exam_answers',
            'exam_attempts',
            'exam_batches',
            'package_exam',
            'packages',
            'exam_questions',
            'exam_sections',
            'exams',
            'question_bank_sections',
            'question_options',
            'question_passages',
            'questions',
            'question_banks',
        ] as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->truncate();
            }
        }

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        // irreversible on purpose
    }
};

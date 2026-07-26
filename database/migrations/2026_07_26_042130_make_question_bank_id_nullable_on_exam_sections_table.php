<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * question_bank_id perlu boleh NULL karena section untuk Part Latihan
     * Topik (dibuat via TopicPartGenerator) tidak terikat ke satu bank soal
     * tertentu -- soalnya diambil random dari kumpulan soal per topic_id,
     * bisa lintas bank. question_bank_id tetap wajib diisi untuk section
     * milik Exam try-out biasa (yang attach bank soal manual).
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE exam_sections MODIFY question_bank_id BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE exam_sections MODIFY question_bank_id BIGINT UNSIGNED NOT NULL');
    }
};

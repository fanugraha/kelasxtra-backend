<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// P2 poin 6: exams.context sebagai discriminator eksplisit ('tryout' |
// 'topic_practice'), menggantikan inferensi implisit dari
// whereNull('topic_id')/whereNotNull('part_number') yang tersebar di
// ExamController, ExamResource, dan TopicPracticeController. topic_id dan
// part_number TETAP dipertahankan (masih dipakai untuk relasi & urutan
// Part) -- context hanya jadi satu-satunya sumber kebenaran untuk
// "exam jenis apa ini", bukan pengganti kolom yang sudah ada.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->string('context')->default('tryout')->after('topic_id');
        });

        // Backfill berdasarkan aturan lama yang sudah terbukti benar di
        // seluruh codebase saat ini: exam dengan topic_id terisi adalah
        // Part latihan topik, sisanya tryout biasa.
        DB::table('exams')->whereNotNull('topic_id')->update(['context' => 'topic_practice']);

        Schema::table('exams', function (Blueprint $table) {
            $table->index('context');
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropIndex(['context']);
            $table->dropColumn('context');
        });
    }
};

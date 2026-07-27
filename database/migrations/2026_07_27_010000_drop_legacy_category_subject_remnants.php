<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P1 cleanup: buang sisa-sisa sistem categories/subjects lama yang sudah
 * digantikan penuh oleh `taxonomies`.
 *
 * Diverifikasi sebelum migration ini dibuat:
 *  - `categories` & `subjects` sudah 0 baris, baik di local maupun production.
 *  - Tidak ada Model (Category sudah tidak ada file-nya, Subject::class sudah
 *    tidak direferensikan di app/ manapun) atau FK aktif yang masih menunjuk
 *    ke dua tabel ini.
 *  - `legacy_taxonomy_id` (exam_sections) dan `legacy_category_id` /
 *    `legacy_subject_id` (taxonomies) cuma pernah dibaca oleh migration
 *    backfill satu-kali yang sudah dieksekusi (seed_taxonomies_from_
 *    categories_and_subjects, remap_exam_sections_taxonomy_id) -- tidak ada
 *    Model fillable/cast atau kode aplikasi yang membaca kolom ini sekarang.
 *
 * down() sengaja TIDAK merekonstruksi data (tabel sudah kosong, kolom legacy
 * sudah tidak diisi ulang oleh aplikasi manapun) -- cuma mengembalikan bentuk
 * skema kalau migration ini perlu di-rollback.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_sections', function (Blueprint $table) {
            $table->dropColumn('legacy_taxonomy_id');
        });

        Schema::table('taxonomies', function (Blueprint $table) {
            $table->dropIndex(['legacy_category_id']);
            $table->dropIndex(['legacy_subject_id']);
            $table->dropColumn(['legacy_category_id', 'legacy_subject_id']);
        });

        Schema::dropIfExists('categories');
        Schema::dropIfExists('subjects');
    }

    public function down(): void
    {
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->unsignedInteger('passing_grade')->nullable();
            $table->boolean('requires_passage')->default(false);
            $table->timestamps();

            $table->unique(['program_id', 'code']);
        });

        Schema::table('taxonomies', function (Blueprint $table) {
            $table->unsignedBigInteger('legacy_category_id')->nullable();
            $table->unsignedBigInteger('legacy_subject_id')->nullable();
            $table->index('legacy_category_id');
            $table->index('legacy_subject_id');
        });

        Schema::table('exam_sections', function (Blueprint $table) {
            $table->unsignedBigInteger('legacy_taxonomy_id')->nullable();
        });
    }
};

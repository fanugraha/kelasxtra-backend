<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bagian Ujian (ExamSection) sekarang bisa dikelompokkan pakai
        // Kategori (brand CPNS/BUMN) ATAU Mapel (brand Sekolah/Masuk
        // Kuliah) -- sebelumnya category_id wajib diisi, sekarang jadi
        // opsional karena section dari Bank Soal bermode subject tidak
        // akan punya category_id sama sekali.
        Schema::table('exam_sections', function (Blueprint $table) {
            $table->foreignId('subject_id')->nullable()->after('category_id')
                ->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('exam_sections', function (Blueprint $table) {
            $table->dropConstrainedForeignId('subject_id');
            $table->foreignId('category_id')->nullable(false)->change();
        });
    }
};

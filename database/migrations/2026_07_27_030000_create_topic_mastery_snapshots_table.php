<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// P2 poin 7: read-path rollup untuk topic performance. Ditulis sebagai
// time-series (user_id, topic_id, period) -- BUKAN 1 baris per user+topic
// yang di-overwrite -- supaya dashboard orang tua & recommendation engine
// bisa menunjukkan progres dari waktu ke waktu ("naik dari 40% ke 65%
// dalam sebulan"), bukan cuma foto kondisi terkini. period pakai format
// ISO week yang sama dengan PracticeLeaderboardService (o-Www, mis.
// "2026-W30") supaya konsisten dengan konvensi yang sudah ada.
//
// trend dihitung SEKALI saat baris ini dibuat (lihat TopicMasteryService)
// dan disimpan di sini -- bukan dihitung ulang independen oleh masing-
// masing dari 3 consumer (dashboard siswa, dashboard orang tua,
// recommendation engine) -- supaya ketiganya selalu menampilkan angka
// yang sama persis.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('topic_mastery_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('topic_id')->constrained('topics')->cascadeOnDelete();
            $table->string('period'); // format "o-Www", mis. "2026-W30"
            $table->unsignedInteger('correct_count')->default(0);
            $table->unsignedInteger('total_count')->default(0);
            $table->unsignedTinyInteger('percentage')->default(0); // 0-100, dibulatkan
            $table->string('trend')->nullable(); // 'up' | 'down' | 'stable' | null (belum ada pembanding)
            $table->timestamp('computed_at');
            $table->timestamps();

            $table->unique(['user_id', 'topic_id', 'period']);
            $table->index(['user_id', 'topic_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topic_mastery_snapshots');
    }
};

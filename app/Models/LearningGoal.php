<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P3 scaffolding (27 Jul 2026): target belajar personal per user per program.
 *
 * PENTING -- keputusan produk: fitur ini HANYA untuk program dengan
 * question_grouping_mode = 'subject' (brand Sekolah/SNBT-UTBK). Program
 * CPNS/BUMN (mode 'category') sudah punya passing grade nasional yang fixed,
 * jadi tidak butuh target skor personal. Belum ada service/controller yang
 * memakai model ini -- ditambahkan agar fitur berikutnya tidak perlu bikin
 * migrasi skema dari nol.
 *
 * Rencana penggunaan nanti:
 * - target_score null -> tampilkan skor rasionalisasi/standar default sistem
 * - target_score diisi -> user/orang tua override manual
 * - guard "hanya untuk mode subject" ditegakkan di application layer saat
 *   membuat/menampilkan goal, BUKAN lewat constraint database, supaya mudah
 *   diubah kalau kebutuhan produk berubah.
 */
class LearningGoal extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'program_id',
        'target_score',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'target_score' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }
}

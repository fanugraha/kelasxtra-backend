<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class QuestionBank extends Model
{
    use HasFactory;
    protected $fillable = [
        'taxonomy_id',
        'program_id',
        'title',
        'scoring_type',
        'point_correct',
        'point_wrong',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $bank) {
            if (blank($bank->program_id) && blank($bank->taxonomy_id)) {
                throw new \InvalidArgumentException('Question bank harus punya program_id atau taxonomy_id.');
            }

            if (filled($bank->program_id) && blank($bank->taxonomy_id)) {
                throw new \InvalidArgumentException('Question bank yang terikat Program harus punya taxonomy_id.');
            }

            // P1.5: scoring_type dulu di-copy ke exam_sections.scoring_type
            // saat attach (lihat Exam::attachBank()) dan dijaga sinkron lewat
            // write-side hook di static::saved() -- itu sudah dihapus.
            // ExamSection::scoringType() sekarang baca LIVE dari bank ini
            // via accessor begitu section punya question_bank_id, jadi tidak
            // ada lagi kolom kedua yang perlu ditulis ulang/bisa basi.
            //
            // Guard di bawah ini TETAP diperlukan meski bacaannya sudah
            // live: begitu bank sudah dipakai untuk menilai sungguhan
            // (ExamAttemptSectionScore ada), scoring_type-nya tidak boleh
            // berubah lagi -- kalau dibiarkan, semua section yang attach ke
            // bank ini (termasuk yang attempt-nya sudah lama selesai
            // dinilai) akan langsung ikut berubah cara baca skornya di
            // ExamScoringService begitu bank di-update, tanpa jejak apapun.
            // Pola blokirnya sama seperti Package::deleting() dan
            // Exam::detachSection(): berdasarkan ada-tidaknya
            // ExamAttemptSectionScore nyata, bukan ada-tidaknya relasi
            // struktural ke section.
            if ($bank->exists && $bank->isDirty('scoring_type')) {
                $sectionIds = $bank->examSections()->pluck('id');

                $hasGradedAttempts = ExamAttemptSectionScore::whereIn('exam_section_id', $sectionIds)->exists();

                if ($hasGradedAttempts) {
                    throw new \RuntimeException(
                        "Bank Soal \"{$bank->title}\" (#{$bank->id}) tidak bisa diubah scoring_type-nya karena ".
                        'sudah ada siswa yang mengerjakan dan mendapat nilai di section yang memakai bank ini. '.
                        'Buat Bank Soal baru kalau perlu ubah cara penilaian.'
                    );
                }
            }
        });
    }

    public function taxonomy(): BelongsTo
    {
        return $this->belongsTo(Taxonomy::class);
    }
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class, 'bank_id');
    }
    public function examSections(): HasMany
    {
        return $this->hasMany(ExamSection::class, 'question_bank_id');
    }
    public function passages(): HasMany
    {
        return $this->hasMany(QuestionPassage::class);
    }
}

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

            // scoring_type di-copy ke exam_sections.scoring_type saat attach
            // (lihat Exam::attachBank()) -- section Latihan Topik yang tidak
            // terikat bank (question_bank_id NULL) butuh kolom sendiri, jadi
            // kolom ini TIDAK bisa dihapus/selalu-baca-dari-bank begitu saja.
            //
            // Tapi copy itu artinya bisa basi: ubah scoring_type di sini
            // setelah section sudah attach, section-nya tidak otomatis ikut
            // berubah kecuali kita sync manual (lihat static::saved() di
            // bawah). Supaya perubahan itu tidak diam-diam mengubah cara
            // attempt LAMA seharusnya sudah dinilai, kita pakai pola yang
            // sama seperti Package::deleting() dan Exam::detachSection():
            // blokir berdasarkan ada-tidaknya ExamAttemptSectionScore nyata,
            // bukan berdasarkan ada-tidaknya relasi struktural ke section.
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

        // Belum ada attempt yang ter-grade (dijamin oleh guard di saving()
        // di atas) -- aman sync scoring_type ke semua section yang sudah
        // attach ke bank ini, supaya exam_sections.scoring_type tidak diam-
        // diam basi dibanding bank sumbernya.
        static::saved(function (self $bank) {
            if ($bank->wasChanged('scoring_type')) {
                $bank->examSections()->update(['scoring_type' => $bank->scoring_type]);
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

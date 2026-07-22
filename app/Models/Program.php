<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Program extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
        'is_active',
        'question_grouping_mode',
    ];

    /**
     * 'category' -> brand CPNS/BUMN (banyak Bagian Ujian sekaligus per exam).
     * 'subject'  -> brand Sekolah/Masuk Kuliah (latihan per Mapel, satu-satu).
     */
    public function usesSubjectMode(): bool
    {
        return $this->question_grouping_mode === 'subject';
    }
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
    public function packages(): HasMany
    {
        return $this->hasMany(Package::class);
    }
    public function questionBanks(): HasMany
    {
        return $this->hasMany(QuestionBank::class);
    }
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    // Subject bersifat global (tidak punya program_id sendiri). Relasinya ke
    // Program ini cuma tidak langsung, lewat question_banks yang menyimpan
    // program_id + subject_id sekaligus. Dipakai buat SubjectsRelationManager
    // (read-only) supaya admin bisa lihat Mapel apa saja yang sudah dipakai
    // di Program mode 'subject' ini.
    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'question_banks', 'program_id', 'subject_id')
            ->distinct();
    }
}

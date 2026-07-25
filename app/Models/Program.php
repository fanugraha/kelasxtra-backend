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

    // Kategori Soal sekarang disimpan di tabel taxonomies (type='category'),
    // masih terikat ke Program ini lewat taxonomies.program_id.
    public function categories(): HasMany
    {
        return $this->hasMany(Taxonomy::class)->categories();
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    // Mapel bersifat global (taxonomies.program_id kosong untuk type='subject').
    // Relasinya ke Program ini tidak langsung, lewat question_banks yang
    // menyimpan program_id + taxonomy_id sekaligus. Dipakai buat
    // SubjectsRelationManager (read-only) supaya admin bisa lihat Mapel apa
    // saja yang sudah dipakai di Program mode 'subject' ini.
    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Taxonomy::class, 'question_banks', 'program_id', 'taxonomy_id')
            ->where('taxonomies.type', 'subject')
            ->distinct();
    }

    public function subscriptionPlans()
    {
        return $this->hasMany(\App\Models\SubscriptionPlan::class);
    }

    public function subscriptions()
    {
        return $this->belongsToMany(\App\Models\Subscription::class, 'subscription_programs');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Auth\MustVerifyEmail;
use Laravel\Sanctum\HasApiTokens;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;

class User extends Authenticatable implements \Illuminate\Contracts\Auth\MustVerifyEmail, FilamentUser
{
    use HasApiTokens, HasFactory, MustVerifyEmail, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'google_id',
        'password',
        'phone',
        'role',
        'level_pendidikan',
        'is_active',
        'hide_from_leaderboard_feed',
        'parent_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'hide_from_leaderboard_feed' => 'boolean',
        ];
    }

    // Hanya admin/tutor yang boleh masuk panel Filament.
    // Siswa tetap login lewat API (Sanctum), bukan panel admin.
    public function canAccessPanel(Panel $panel): bool
    {
        return in_array($this->role, ['admin', 'tutor']);
    }

    public function tutor(): HasOne
    {
        return $this->hasOne(Tutor::class);
    }

    // P3 scaffolding: 1 orang tua (role 'orang_tua') -> banyak anak.
    // 1 anak cukup punya 1 orang tua, jadi FK biasa (bukan pivot).
    // Belum ada endpoint yang memakai ini -- disiapkan untuk fitur
    // dashboard multi-anak yang akan dikerjakan kemudian.
    public function parent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(User::class, 'parent_id');
    }

    // P3 scaffolding: target belajar, hanya relevan untuk program
    // question_grouping_mode = 'subject' (brand Sekolah/SNBT). Guard-nya
    // ada di service/controller, bukan di relasi ini.
    public function learningGoals(): HasMany
    {
        return $this->hasMany(LearningGoal::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function examAttempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class);
    }

    public function leaderboardSnapshots(): HasMany
    {
        return $this->hasMany(LeaderboardSnapshot::class);
    }

    public function testimonials(): HasMany
    {
        return $this->hasMany(Testimonial::class);
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class, 'author_id');
    }
}

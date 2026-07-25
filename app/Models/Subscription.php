<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Builder;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'plan_id',
        'transaction_id',
        'status',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(Program::class, 'subscription_programs');
    }

    // Niru pola Enrollment::scopeActive() -- status aktif DAN belum lewat end_date
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', now());
            });
    }

    // Cek apakah subscription ini meng-cover satu program tertentu --
    // baik lewat plan yang fix ke 1 program, maupun lewat pivot
    // subscription_programs (untuk plan multi-select).
    public function coversProgram(int $programId): bool
    {
        if ($this->plan->isFixedProgram()) {
            return $this->plan->program_id === $programId;
        }

        return $this->programs()->where('programs.id', $programId)->exists();
    }
}

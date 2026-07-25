<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'tagline',
        'description',
        'features',
        'duration_days',
        'program_slot_count',
        'program_id',
        'price',
        'is_active',
        'is_featured',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'price' => 'decimal:2',
        'features' => 'array',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'plan_id');
    }

    // Plan fix ke 1 program (program_slot_count null) vs plan multi-select
    public function isFixedProgram(): bool
    {
        return is_null($this->program_slot_count);
    }

    // Harga per hari -- anchor psikologis di card (mis. "≈ Rp5.000/hari"),
    // pola umum di Netflix/Spotify supaya harga total terasa lebih ringan.
    public function pricePerDay(): float
    {
        if (! $this->duration_days) {
            return 0;
        }

        return round((float) $this->price / $this->duration_days, 2);
    }
}

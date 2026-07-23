<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Package extends Model
{
    protected $appends = ['category'];

    use HasFactory;

    protected $fillable = [
        'program_id',
        'taxonomy_id',
        'name',
        'type',
        'is_focus_topic',
        'focus_taxonomy_id',
        'price',
        'discount_price',
        'duration_days',
        'description',
        'features',
        'materi',
        'banner_image_url',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'discount_price' => 'decimal:2',
            'features' => 'array',
            'materi' => 'array',
            'is_focus_topic' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $package) {
            if (blank($package->program_id) && blank($package->taxonomy_id)) {
                throw new \InvalidArgumentException('Package harus punya program_id atau taxonomy_id.');
            }
        });
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function taxonomy(): BelongsTo
    {
        return $this->belongsTo(Taxonomy::class);
    }

    public function focusTaxonomy(): BelongsTo
    {
        return $this->belongsTo(Taxonomy::class, 'focus_taxonomy_id');
    }

    public function getCategoryAttribute()
    {
        $taxonomy = $this->is_focus_topic ? $this->focusTaxonomy : $this->taxonomy;

        if (! $taxonomy) {
            return null;
        }

        return [
            'id' => $taxonomy->id,
            'code' => $taxonomy->code,
            'name' => $taxonomy->name,
        ];
    }

    public function classes(): HasMany
    {
        return $this->hasMany(ClassRoom::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function exams(): BelongsToMany
    {
        return $this->belongsToMany(Exam::class, 'package_exam');
    }
}

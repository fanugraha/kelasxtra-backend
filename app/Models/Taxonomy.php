<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Taxonomy extends Model
{
    protected $fillable = [
        'program_id',
        'type',
        'code',
        'name',
        'passing_grade',
        'requires_passage',
    ];

    protected $casts = [
        'requires_passage' => 'boolean',
        'passing_grade'    => 'integer',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function scopeCategories(Builder $query): Builder
    {
        return $query->where('type', 'category');
    }

    public function scopeSubjects(Builder $query): Builder
    {
        return $query->where('type', 'subject');
    }

    public function isCategory(): bool
    {
        return $this->type === 'category';
    }

    public function isSubject(): bool
    {
        return $this->type === 'subject';
    }
}

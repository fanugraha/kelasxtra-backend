<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = [
        'program_id',
        'code',
        'name',
        'passing_grade',
        'requires_passage',
    ];

    protected $casts = [
        'requires_passage' => 'boolean',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    public function questionBankSections(): HasMany
    {
        return $this->hasMany(QuestionBankSection::class);
    }

    public function examSections(): HasMany
    {
        return $this->hasMany(ExamSection::class);
    }
}

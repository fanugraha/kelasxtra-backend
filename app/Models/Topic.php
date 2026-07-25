<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Topic extends Model
{
    protected $fillable = ['taxonomy_id', 'code', 'name'];

    public function taxonomy(): BelongsTo
    {
        return $this->belongsTo(Taxonomy::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class)->orderBy('part_number');
    }

    // Dipakai TopicResource buat hitung Sisa Stok tanpa N+1 query -- lihat
    // TopicPartGenerator, tiap soal yang sudah "dipakai" untuk sebuah Part
    // dicatat 1 baris di sini (per topic_id + question_id).
    public function usedQuestions(): HasMany
    {
        return $this->hasMany(TopicUsedQuestion::class);
    }
}

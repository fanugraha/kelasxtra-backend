<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionBankSection extends Model
{
    protected $fillable = [
        'question_bank_id',
        'category_id',
        'target_count',
    ];

    public function questionBank(): BelongsTo
    {
        return $this->belongsTo(QuestionBank::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    // Jumlah soal yang sudah beneran keisi untuk section ini —
    // dihitung dari data asli (questions), bukan disimpan manual.
    // Dipakai buat progress bar "12/30" di tab kategori Filament nanti.
    public function filledCount(): int
    {
        return Question::where('bank_id', $this->question_bank_id)
            ->where('category_id', $this->category_id)
            ->count();
    }
}

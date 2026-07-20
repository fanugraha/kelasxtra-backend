<?php

namespace App\Filament\Resources\ExamResource\Concerns;

use App\Models\Exam;
use Illuminate\Support\Collection;

/**
 * Logic auto-match exam_section_id berdasarkan category_id soal, dipakai
 * bareng oleh CreateExam (attach pertama kali) dan EditExam (sync waktu
 * admin ubah centang Bank Soal). SEBELUM diekstrak ke sini, EditExam selalu
 * mengisi exam_section_id => null waktu attach -- itu penyebab soal baru
 * yang ditambahkan lewat Edit selalu "kosong section"-nya walau exam-nya
 * sudah punya section yang cocok kategorinya (lihat Exam #10 di hasil audit).
 */
trait ResolvesExamSectionForQuestion
{
    /**
     * @return Collection<int, \App\Models\ExamSection> keyed by category_id
     */
    protected function sectionsByCategory(Exam $exam): Collection
    {
        return $exam->sections()->get(['id', 'category_id'])->keyBy('category_id');
    }

    protected function resolveSectionId(?int $questionCategoryId, Collection $sectionByCategory): ?int
    {
        return $questionCategoryId
            ? $sectionByCategory->get($questionCategoryId)?->id
            : null;
    }
}

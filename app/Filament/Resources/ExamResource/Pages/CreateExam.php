<?php

namespace App\Filament\Resources\ExamResource\Pages;

use App\Filament\Resources\ExamResource;
use App\Models\Exam;
use App\Models\Question;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateExam extends CreateRecord
{
    protected static string $resource = ExamResource::class;

    protected function afterCreate(): void
    {
        $this->attachQuestionsFromBank($this->record);
    }

    /**
     * Tarik semua soal dari bank_id yang dipilih, attach ke exam ini.
     * exam_section_id LANGSUNG diisi dari kategori tiap soal (kalau exam
     * ini sudah punya section yang cocok kategorinya) -- bukan di-null-kan
     * kayak sebelumnya. Kalau exam belum punya section untuk kategori itu,
     * dibiarkan null dan bisa dilengkapi lewat "Generate dari Program" +
     * tombol assign ulang di panel Soal.
     */
    protected function attachQuestionsFromBank(Exam $exam): void
    {
        if (!$exam->bank_id) {
            return;
        }

        $questions = Question::where('bank_id', $exam->bank_id)->get(['id', 'category_id']);

        if ($questions->isEmpty()) {
            return;
        }

        $sectionByCategory = $exam->sections()->get(['id', 'category_id'])->keyBy('category_id');

        $syncData = $questions->mapWithKeys(function ($question) use ($sectionByCategory) {
            $sectionId = $question->category_id
                ? $sectionByCategory->get($question->category_id)?->id
                : null;

            return [$question->id => ['points' => 1, 'exam_section_id' => $sectionId]];
        })->all();

        $exam->questions()->attach($syncData);

        Notification::make()
            ->title(count($syncData) . ' soal dari bank ditambahkan ke exam ini.')
            ->success()
            ->send();
    }
}

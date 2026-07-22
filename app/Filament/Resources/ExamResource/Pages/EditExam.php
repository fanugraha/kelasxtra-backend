<?php

namespace App\Filament\Resources\ExamResource\Pages;

use App\Filament\Resources\ExamResource;
use App\Models\Exam;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;

class EditExam extends EditRecord
{
    protected static string $resource = ExamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Sama seperti pengecekan di ExamResource::table() -- tabel listing
            // dan halaman Edit ini masing-masing punya instance DeleteAction
            // sendiri di Filament, jadi pengecekan harus diduplikasi di sini
            // juga supaya delete dari halaman Edit tidak lolos tanpa dicek.
            DeleteAction::make()
                ->before(function (DeleteAction $action, Exam $record) {
                    $sectionIds = $record->sections()->pluck('id');

                    $questionCount = DB::table('exam_questions')
                        ->whereIn('exam_section_id', $sectionIds)
                        ->count();

                    if ($questionCount > 0) {
                        Notification::make()
                            ->title('Tidak bisa menghapus Exam')
                            ->body("Exam \"{$record->title}\" masih punya {$questionCount} soal ter-attach lewat Bagian Ujian-nya. Lepas soal-soal itu dari semua Bagian Ujian dulu sebelum menghapus Exam ini.")
                            ->danger()
                            ->send();

                        $action->halt();
                    }
                }),
        ];
    }
}

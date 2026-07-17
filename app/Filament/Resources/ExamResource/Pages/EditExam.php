<?php

namespace App\Filament\Resources\ExamResource\Pages;

use App\Filament\Resources\ExamResource;
use App\Models\Exam;
use App\Models\Question;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditExam extends EditRecord
{
    protected static string $resource = ExamResource::class;

    protected array $pendingBankIds = [];

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    /**
     * Prefill field bank_ids pas halaman Edit dibuka, supaya checkbox
     * langsung menunjukkan bank mana saja yang soalnya sudah nempel --
     * bukan selalu kosong seperti sebelumnya.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['bank_ids'] = $this->record->questions()
            ->pluck('bank_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $data;
    }

    /**
     * bank_ids adalah field virtual (bukan kolom di tabel exams). Ambil
     * nilainya DI SINI, unset supaya tidak ikut dikirim ke $record->update(),
     * dan tentukan bank_id (kategorisasi utama -- kolom asli yang masih
     * dipakai bagian lain sistem): pertahankan yang lama kalau masih
     * dicentang, supaya labelnya tidak berubah-ubah tiap kali disave.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->pendingBankIds = $data['bank_ids'] ?? [];
        unset($data['bank_ids']);

        if (!empty($this->pendingBankIds)) {
            $data['bank_id'] = in_array($this->record->bank_id, $this->pendingBankIds)
                ? $this->record->bank_id
                : $this->pendingBankIds[0];
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $this->syncQuestionsWithBanks($this->record, $this->pendingBankIds);
    }

    /**
     * Samakan soal yang nempel di exam dengan bank-bank yang dipilih admin:
     * bank yang dicentang -> soalnya di-attach (kalau belum ada).
     * bank yang di-uncheck -> soalnya di-detach dari exam ini.
     */
    protected function syncQuestionsWithBanks(Exam $exam, array $bankIds): void
    {
        if (empty($bankIds)) {
            return;
        }

        $questionIdsToKeep = Question::whereIn('bank_id', $bankIds)->pluck('id');
        $currentlyAttached = $exam->questions()->pluck('questions.id');

        $toAttach = $questionIdsToKeep->diff($currentlyAttached);
        $toDetach = $currentlyAttached->diff($questionIdsToKeep);

        if ($toAttach->isNotEmpty()) {
            $syncData = $toAttach->mapWithKeys(fn ($id) => [$id => ['points' => 1, 'exam_section_id' => null]])->all();
            $exam->questions()->attach($syncData);
        }

        if ($toDetach->isNotEmpty()) {
            $exam->questions()->detach($toDetach);
        }

        if ($toAttach->isNotEmpty() || $toDetach->isNotEmpty()) {
            Notification::make()
                ->title("Bank soal diperbarui: {$toAttach->count()} soal ditambahkan, {$toDetach->count()} soal dilepas.")
                ->success()
                ->send();
        }
    }
}

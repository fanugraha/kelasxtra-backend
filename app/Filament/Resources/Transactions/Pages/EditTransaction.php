<?php

namespace App\Filament\Resources\Transactions\Pages;

use App\Filament\Resources\Transactions\TransactionResource;
use Filament\Resources\Pages\EditRecord;

class EditTransaction extends EditRecord
{
    protected static string $resource = TransactionResource::class;

    protected ?string $statusBeforeSave = null;

    protected function getHeaderActions(): array
    {
        return [
            TransactionResource::markSuccessAction(),
        ];
    }

    // Simpan status SEBELUM form disimpan — $this->record di titik ini masih
    // punya attribute lama.
    protected function beforeSave(): void
    {
        $this->statusBeforeSave = $this->record->status;
    }

    // Bandingkan status lama vs baru setelah tersimpan. Kalau berubah, catat
    // entri audit trail terpisah dari log webhook/reconcile.
    protected function afterSave(): void
    {
        if ($this->statusBeforeSave === null) {
            return;
        }

        if ($this->statusBeforeSave === $this->record->status) {
            return;
        }

        $this->record->logs()->create([
            'raw_payload' => [
                'from_status' => $this->statusBeforeSave,
                'to_status' => $this->record->status,
                'changed_by_name' => auth()->user()?->name,
            ],
            'source' => 'admin_manual',
            'changed_by' => auth()->id(),
        ]);
    }
}

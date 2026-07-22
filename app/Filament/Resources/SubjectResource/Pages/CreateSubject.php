<?php

namespace App\Filament\Resources\SubjectResource\Pages;

use App\Filament\Resources\SubjectResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSubject extends CreateRecord
{
    protected static string $resource = SubjectResource::class;

    // SubjectResource sekarang pakai model Taxonomy (tabel gabungan), jadi
    // waktu admin bikin Mapel baru lewat menu ini, kita perlu tandain
    // otomatis bahwa ini tipe 'subject' -- admin gak perlu isi manual.
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['type'] = 'subject';

        return $data;
    }
}

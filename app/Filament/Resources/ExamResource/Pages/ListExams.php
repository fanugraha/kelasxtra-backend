<?php

namespace App\Filament\Resources\ExamResource\Pages;

use App\Filament\Resources\ExamResource;
use App\Models\Exam;
use App\Models\Program;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListExams extends ListRecords
{
    protected static string $resource = ExamResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }

    /**
     * Tab per Program, pola sama seperti Bank Soals & Topics. PENTING:
     * baik query tab maupun badge count di sini SELALU exclude Part
     * Latihan (whereNull('topic_id')) -- konsisten sama filter default
     * "sembunyikan_part" di ExamResource::table(). Part Latihan dikelola
     * di halaman Topics, jadi nggak boleh ikut kehitung/kelihatan di sini
     * walau tab lagi aktif, supaya angkanya nggak nyesatin admin.
     */
    public function getTabs(): array
    {
        return [];
    }
}

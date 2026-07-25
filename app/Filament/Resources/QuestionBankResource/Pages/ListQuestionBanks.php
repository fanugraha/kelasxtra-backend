<?php

namespace App\Filament\Resources\QuestionBankResource\Pages;

use App\Filament\Resources\QuestionBankResource;
use App\Models\Program;
use App\Models\QuestionBank;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListQuestionBanks extends ListRecords
{
    protected static string $resource = QuestionBankResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }

    /**
     * Tab per Program + badge jumlah bank soal di tiap tab. Angka dihitung
     * lewat 1 query groupBy (bukan query per-tab di dalam loop) supaya
     * jumlah Program berapapun tetap cuma 1 query tambahan -- pola yang
     * sama dipakai TopicResource buat kolom "Jumlah Soal" (withCount),
     * cuma di sini levelnya per-tab bukan per-baris.
     */
    public function getTabs(): array
    {
        return [];
    }
}

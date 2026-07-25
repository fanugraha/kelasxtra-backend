<?php

namespace App\Filament\Resources\TopicResource\Pages;

use App\Filament\Resources\TopicResource;
use App\Models\Program;
use App\Models\Topic;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListTopics extends ListRecords
{
    protected static string $resource = TopicResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }

    /**
     * Tab per Program + badge jumlah topik, pola sama seperti Bank Soals
     * & Exams. Topic tidak punya program_id langsung (cuma taxonomy_id),
     * jadi hitung agregatnya lewat join ke taxonomies -- tetap 1 query,
     * bukan query per-tab di dalam loop.
     */
    public function getTabs(): array
    {
        return [];
    }
}

<?php

namespace App\Filament\Resources\Packages\Pages;

use App\Filament\Resources\Packages\PackageResource;
use App\Models\Package;
use App\Models\Program;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListPackages extends ListRecords
{
    protected static string $resource = PackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * Tab per Program + badge jumlah paket, pola sama seperti Bank Soals/
     * Topics/Exams. Package punya program_id langsung, jadi query-nya
     * simpel (sama seperti QuestionBank), bukan lewat join/whereHas.
     */
    public function getTabs(): array
    {
        return [];
    }
}

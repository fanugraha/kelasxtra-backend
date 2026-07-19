<?php

namespace App\Filament\Resources\Promos\Pages;

use App\Filament\Resources\Promos\PromoResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePromo extends CreateRecord
{
    protected static string $resource = PromoResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if ($data['unlimited_time'] ?? false) {
            $data['valid_until'] = null;
        }
        unset($data['unlimited_time']);

        return $data;
    }
}

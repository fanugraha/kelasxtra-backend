<?php
namespace App\Filament\Resources\TopicResource\Pages;

use App\Filament\Resources\TopicResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTopic extends EditRecord
{
    protected static string $resource = TopicResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    public function getRelationManagers(): array
    {
        return [
            \App\Filament\Resources\TopicResource\RelationManagers\PartsRelationManager::class,
        ];
    }
}

<?php

namespace App\Filament\Resources\Materials\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class MaterialForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('class_id')
                    ->relationship('classRoom', 'name')
                    ->label('Kelas')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('title')
                    ->label('Judul')
                    ->required(),
                TextInput::make('file_url')
                    ->label('URL File')
                    ->url()
                    ->required(),
                Select::make('type')
                    ->label('Tipe')
                    ->options(['pdf' => 'Pdf', 'video_link' => 'Video link'])
                    ->required(),
            ]);
    }
}

<?php

namespace App\Filament\Resources\Packages\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class PackageInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('program.name')
                    ->label('Program')
                    ->placeholder('-'),
                TextEntry::make('subject.name')
                    ->label('Mata Pelajaran')
                    ->placeholder('-'),
                TextEntry::make('name'),
                TextEntry::make('type')
                    ->badge(),
                TextEntry::make('price')
                    ->money('IDR'),
                TextEntry::make('discount_price')
                    ->money('IDR')
                    ->placeholder('-'),
                TextEntry::make('duration_days')
                    ->label('Durasi Akses (hari)')
                    ->numeric()
                    ->placeholder('Akses selamanya (lifetime)'),
                TextEntry::make('features')
                    ->label('Fitur Paket')
                    ->listWithLineBreaks()
                    ->bulleted()
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('materi')
                    ->label('Daftar Materi')
                    ->listWithLineBreaks()
                    ->bulleted()
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('description')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}

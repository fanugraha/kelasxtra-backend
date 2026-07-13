<?php

namespace App\Filament\Resources\Tutors\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class TutorInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('user.name')
                    ->label('User'),
                TextEntry::make('bio')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('expertise')
                    ->placeholder('-'),
                TextEntry::make('cv_url')
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}

<?php

namespace App\Filament\Resources\Tutors\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class TutorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required(),
                Textarea::make('bio')
                    ->columnSpanFull(),
                TextInput::make('expertise'),
                TextInput::make('cv_url')
                    ->url(),
            ]);
    }
}

<?php

namespace App\Filament\Resources\ClassRooms\Schemas;

use App\Models\Tutor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ClassRoomForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('package_id')
                    ->relationship('package', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('tutor_id')
                    ->label('Tutor')
                    ->options(fn () => Tutor::with('user')->get()->mapWithKeys(
                        fn (Tutor $tutor) => [$tutor->id => $tutor->user?->name ?? "Tutor #{$tutor->id}"]
                    ))
                    ->searchable()
                    ->required(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('capacity')
                    ->required()
                    ->numeric()
                    ->default(0),
                Select::make('status')
                    ->options(['active' => 'Active', 'full' => 'Full', 'inactive' => 'Inactive', 'completed' => 'Completed'])
                    ->default('active')
                    ->required(),
            ]);
    }
}
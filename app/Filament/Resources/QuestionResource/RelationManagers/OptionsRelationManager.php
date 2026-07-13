<?php

namespace App\Filament\Resources\QuestionResource\RelationManagers;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Read-only, khusus untuk melihat opsi jawaban di halaman arsip.
 * Pengelolaan opsi jawaban yang benar ada di panel "Soal" dalam
 * Bank Soal (QuestionsRelationManager), bukan di sini.
 */
class OptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'options';

    protected static ?string $title = 'Opsi Jawaban (khusus Pilihan Ganda)';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('option_text')
                ->label('Teks opsi')
                ->disabled(),
            Toggle::make('is_correct')
                ->label('Jawaban benar?')
                ->disabled(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('option_text')
            ->columns([
                TextColumn::make('option_text')->label('Opsi'),
                IconColumn::make('is_correct')->label('Benar')->boolean(),
            ]);
    }
}

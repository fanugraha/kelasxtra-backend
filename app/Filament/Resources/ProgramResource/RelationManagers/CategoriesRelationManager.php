<?php

namespace App\Filament\Resources\ProgramResource\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CategoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'categories';

    protected static ?string $title = 'Kategori Soal';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')
                ->label('Kode')
                ->required()
                ->maxLength(50)
                ->helperText('Contoh: twk, tiu, tkp, reading, listening'),
            TextInput::make('name')
                ->label('Nama')
                ->required()
                ->maxLength(255)
                ->helperText('Contoh: Tes Wawasan Kebangsaan'),
            TextInput::make('passing_grade')
                ->label('Passing Grade')
                ->numeric()
                ->nullable(),
            Toggle::make('requires_passage')
                ->label('Butuh Bacaan/Audio Bersama')
                ->helperText('Aktifkan untuk kategori seperti Reading/Listening yang beberapa soal berbagi satu bacaan atau audio.')
                ->default(false),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('code')->badge()->sortable(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('passing_grade')->label('Passing Grade'),
                IconColumn::make('requires_passage')->boolean()->label('Butuh Bacaan'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

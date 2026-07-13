<?php

namespace App\Filament\Resources\ExamResource\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SectionsRelationManager extends RelationManager
{
    protected static string $relationship = 'sections';

    protected static ?string $title = 'Bagian Ujian';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('category_id')
                ->label('Kategori')
                ->relationship('category', 'name')
                ->required()
                ->searchable()
                ->preload(),
            TextInput::make('code')
                ->label('Kode')
                ->required()
                ->maxLength(255)
                ->helperText('Contoh: TWK, TIU, TKP'),
            TextInput::make('name')
                ->label('Nama')
                ->required()
                ->maxLength(255)
                ->helperText('Contoh: "Tes Wawasan Kebangsaan"'),
            TextInput::make('order')
                ->label('Urutan')
                ->numeric()
                ->required()
                ->default(1),
            Select::make('scoring_type')
                ->label('Tipe Penilaian')
                ->options([
                    'single_correct' => 'Single Correct (benar/salah)',
                    'weighted_options' => 'Weighted Options (bobot per opsi)',
                ])
                ->required(),
            TextInput::make('points_per_question')
                ->label('Poin per Soal')
                ->numeric()
                ->required(),
            TextInput::make('min_passing_score')
                ->label('Skor Minimal Lulus')
                ->numeric()
                ->helperText('Kosongkan kalau bagian ini tidak punya passing score sendiri.'),
            TextInput::make('max_score')
                ->label('Skor Maksimal')
                ->numeric()
                ->required(),
            TextInput::make('duration_minutes')
                ->label('Durasi')
                ->numeric()
                ->required()
                ->suffix('menit'),
            Toggle::make('is_locked_after_next')
                ->label('Terkunci setelah lanjut ke bagian berikutnya')
                ->helperText('Kalau aktif, siswa tidak bisa kembali ke bagian ini setelah lanjut ke bagian berikutnya.'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('order')
            ->columns([
                TextColumn::make('order')->label('Urutan')->sortable(),
                TextColumn::make('code')->label('Kode'),
                TextColumn::make('name')->label('Nama'),
                TextColumn::make('category.name')->label('Kategori'),
                TextColumn::make('scoring_type')->badge(),
                TextColumn::make('duration_minutes')->suffix(' menit'),
                TextColumn::make('max_score')->label('Skor Maks'),
                IconColumn::make('is_locked_after_next')->label('Terkunci')->boolean(),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}

<?php

namespace App\Filament\Resources\Packages\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use App\Models\Exam;

class PackageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('program_id')
                    ->label('Program')
                    ->relationship('program', 'name')
                    ->searchable()
                    ->preload()
                    ->requiredWithout('subject_id')
                    ->live()
                    ->helperText('Isi ini kalau paket terikat ke program tryout (CPNS, TOEFL, dll).'),
                Select::make('subject_id')
                    ->label('Mata Pelajaran')
                    ->relationship('subject', 'name')
                    ->searchable()
                    ->preload()
                    ->requiredWithout('program_id')
                    ->live()
                    ->helperText('Isi ini kalau paket berupa latihan/bimbel per-mapel (Matematika, Fisika, dll).'),
                TextInput::make('name')
                    ->required(),
                Select::make('type')
                    ->options([
                        'privat' => 'Privat',
                        'group' => 'Group',
                        'latihan_soal' => 'Latihan soal',
                        'reguler' => 'Reguler',
                    ])
                    ->required()
                    ->live(),
                Select::make('exams')
                    ->label('Exam/Ujian yang Dijual')
                    ->relationship('exams', 'title')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->visible(fn (Get $get) => $get('type') === 'latihan_soal')
                    ->required(fn (Get $get) => $get('type') === 'latihan_soal')
                    ->options(function (Get $get) {
                        $programId = $get('program_id');
                        $subjectId = $get('subject_id');

                        return Exam::query()
                            ->with('bank')
                            ->when($programId, fn ($q) => $q->whereHas('bank', fn ($b) => $b->where('program_id', $programId)))
                            ->when($subjectId, fn ($q) => $q->whereHas('bank', fn ($b) => $b->where('subject_id', $subjectId)))
                            ->get()
                            ->mapWithKeys(fn ($exam) => [$exam->id => $exam->title . ' (' . $exam->bank->title . ')']);
                    })
                    ->helperText('Pilih exam/ujian mana saja yang akan dibuka aksesnya untuk siswa yang membeli paket ini. Exam yang tidak dipilih di sini TIDAK akan bisa diakses meski satu bank soal.'),
                TextInput::make('price')
                    ->required()
                    ->numeric()
                    ->prefix('Rp'),
                TextInput::make('discount_price')
                    ->numeric()
                    ->prefix('Rp'),
                TextInput::make('duration_days')
                    ->required()
                    ->numeric(),
                Textarea::make('description')
                    ->columnSpanFull(),
            ]);
    }
}

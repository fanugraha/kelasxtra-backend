<?php

namespace App\Filament\Resources;

use App\Filament\Resources\QuestionBankResource\Pages;
use App\Filament\Resources\QuestionBankResource\RelationManagers\QuestionsRelationManager;
use App\Models\QuestionBank;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class QuestionBankResource extends Resource
{
    protected static ?string $model = QuestionBank::class;

    protected static ?string $modelLabel = 'Bank Soal';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static string|\UnitEnum|null $navigationGroup = 'Bank Soal & Ujian';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('subject_id')
                ->label('Mapel (opsional, untuk latihan soal harian tanpa Program)')
                ->relationship('subject', 'name')
                ->searchable()
                ->preload(),
            Select::make('program_id')
                ->label('Program')
                ->relationship('program', 'name')
                ->searchable()
                ->preload()
                ->live()
                ->helperText('Isi kalau Bank Soal ini untuk Exam terstruktur (mis. SKD CPNS). Kalau diisi, Kategori di bawah wajib dipilih.'),
            Select::make('category_id')
                ->label('Kategori')
                ->relationship('category', 'name')
                ->searchable()
                ->preload()
                ->required(fn (Get $get) => filled($get('program_id')))
                ->visible(fn (Get $get) => filled($get('program_id')))
                ->helperText('Bank Soal ini HANYA akan berisi soal kategori ini (mis. TWK). Untuk kategori lain, buat Bank Soal terpisah.'),
            TextInput::make('title')->required()->maxLength(255),
            Select::make('scoring_type')
                ->label('Tipe Penilaian')
                ->options([
                    'single_correct' => 'Single Correct (benar/salah, mis. TWK/TIU)',
                    'weighted_options' => 'Weighted Options (bobot per opsi, mis. TKP)',
                ])
                ->live()
                ->visible(fn (Get $get) => filled($get('program_id'))),
            TextInput::make('point_correct')
                ->label('Poin Jika Benar')
                ->numeric()
                ->visible(fn (Get $get) => $get('scoring_type') === 'single_correct')
                ->required(fn (Get $get) => $get('scoring_type') === 'single_correct'),
            TextInput::make('point_wrong')
                ->label('Poin Jika Salah')
                ->numeric()
                ->default(0)
                ->visible(fn (Get $get) => $get('scoring_type') === 'single_correct')
                ->required(fn (Get $get) => $get('scoring_type') === 'single_correct'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable()->sortable(),
                TextColumn::make('subject.name')->label('Mapel'),
                TextColumn::make('program.name')->label('Program'),
                TextColumn::make('category.name')->label('Kategori'),
                TextColumn::make('scoring_type')->badge(),
                TextColumn::make('questions_count')->counts('questions')->label('Jumlah Soal'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getRelations(): array
    {
        return [
            QuestionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListQuestionBanks::route('/'),
            'create' => Pages\CreateQuestionBank::route('/create'),
            'edit' => Pages\EditQuestionBank::route('/{record}/edit'),
        ];
    }
}

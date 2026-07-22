<?php

namespace App\Filament\Resources;

use App\Filament\Resources\QuestionBankResource\Pages;
use App\Filament\Resources\QuestionBankResource\RelationManagers\QuestionsRelationManager;
use App\Models\Program;
use App\Models\QuestionBank;
use App\Models\Taxonomy;
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
            Select::make('program_id')
                ->label('Program')
                ->relationship('program', 'name')
                ->required()
                ->searchable()
                ->preload()
                ->live()
                // Kalau Program diganti (termasuk gonta-ganti mode), pilihan
                // Kategori/Mapel di bawah di-reset supaya tidak ada kombinasi
                // nyasar (misal kepilih Kategori dari Program yang lain).
                ->afterStateUpdated(function (Get $get, $set) {
                    $set('taxonomy_id', null);
                })
                ->helperText('Menentukan apakah Bank Soal ini pakai Kategori (CPNS/BUMN) atau Mapel (Sekolah/Masuk Kuliah), sesuai pola Program ini.'),
            // Satu dropdown ini menggantikan 2 dropdown lama (Kategori & Mapel).
            // Yang muncul dan isinya menyesuaikan mode Program yang dipilih:
            // Program mode Kategori -> pilihan Kategori punya Program itu.
            // Program mode Mapel -> pilihan Mapel (daftar global).
            Select::make('taxonomy_id')
                ->label(fn (Get $get) => Program::find($get('program_id'))?->usesSubjectMode() ? 'Mapel' : 'Kategori')
                ->options(function (Get $get) {
                    $program = Program::find($get('program_id'));
                    if (! $program) {
                        return [];
                    }
                    return $program->usesSubjectMode()
                        ? Taxonomy::subjects()->pluck('name', 'id')
                        : Taxonomy::categories()->where('program_id', $program->id)->pluck('name', 'id');
                })
                ->searchable()
                ->preload()
                ->live()
                ->required(fn (Get $get) => filled($get('program_id')))
                ->visible(fn (Get $get) => filled($get('program_id')))
                ->helperText(fn (Get $get) => Program::find($get('program_id'))?->usesSubjectMode()
                    ? 'Wajib diisi untuk Program bermode Mapel (mis. SNBT). Bank Soal ini HANYA akan berisi soal mapel ini.'
                    : 'Bank Soal ini HANYA akan berisi soal kategori ini (mis. TWK). Untuk kategori lain, buat Bank Soal terpisah.'),
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
                ->minValue(0)
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
                TextColumn::make('program.name')->label('Program'),
                TextColumn::make('taxonomy.name')
                    ->label('Mapel / Kategori')
                    ->placeholder('—'),
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

<?php

namespace App\Filament\Resources;

use App\Filament\Resources\QuestionBankResource\Pages;
use App\Filament\Resources\QuestionBankResource\RelationManagers\QuestionsRelationManager;
use App\Models\Category;
use App\Models\Program;
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
            // Bank Soal SEKARANG wajib punya Program (tidak ada lagi kasus
            // latihan lepas tanpa Program) -- jadi field ini murni bergantung
            // ke mode Program yang dipilih, tanpa cabang "Program kosong".
            Select::make('subject_id')
                ->label('Mapel')
                ->relationship('subject', 'name')
                ->searchable()
                ->preload()
                ->required(fn (Get $get) => Program::find($get('program_id'))?->usesSubjectMode() ?? false)
                ->visible(fn (Get $get) => Program::find($get('program_id'))?->usesSubjectMode() ?? false)
                ->helperText('Wajib diisi untuk Program bermode Mapel (mis. SNBT). Bank Soal ini HANYA akan berisi soal mapel ini.'),
            Select::make('program_id')
                ->label('Program')
                ->relationship('program', 'name')
                ->required()
                ->searchable()
                ->preload()
                ->live()
                // Kategori/Mapel di bawah bergantung ke Program ini -- kalau
                // Program diganti (termasuk gonta-ganti mode), field yang
                // sudah kepilih di-reset supaya tidak ada kombinasi nyasar.
                ->afterStateUpdated(function (Get $get, $set) {
                    $set('category_id', null);
                    $set('subject_id', null);
                })
                ->helperText('Menentukan apakah Bank Soal ini pakai Kategori (CPNS/BUMN) atau Mapel (Sekolah/Masuk Kuliah), sesuai pola Program ini.'),
            // BARU: field Kategori sekarang muncul HANYA kalau Program yang
            // dipilih pakai mode 'category' -- bukan lagi selalu tampil
            // begitu Program diisi. Opsinya juga tetap difilter cuma yang
            // program_id-nya sama dengan Program yang dipilih.
            Select::make('category_id')
                ->label('Kategori')
                ->options(fn (Get $get) => Category::where('program_id', $get('program_id'))->pluck('name', 'id'))
                ->searchable()
                ->preload()
                ->live()
                ->required(fn (Get $get) => filled($get('program_id')) && !Program::find($get('program_id'))?->usesSubjectMode())
                ->visible(fn (Get $get) => filled($get('program_id')) && !Program::find($get('program_id'))?->usesSubjectMode())
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
                TextColumn::make('kelompok')
                    ->label('Mapel / Kategori')
                    ->getStateUsing(fn (QuestionBank $record) => $record->subject?->name ?? $record->category?->name)
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

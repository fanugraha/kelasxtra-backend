<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExamResource\Pages;
use App\Filament\Resources\ExamResource\RelationManagers\BatchesRelationManager;
use App\Filament\Resources\ExamResource\RelationManagers\QuestionsRelationManager;
use App\Filament\Resources\ExamResource\RelationManagers\SectionsRelationManager;
use App\Models\Exam;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ExamResource extends Resource
{
    protected static ?string $model = Exam::class;

    protected static ?string $modelLabel = 'Exam';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|\UnitEnum|null $navigationGroup = 'Bank Soal & Ujian';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('bank_id')
                ->label('Bank Soal untuk Exam Ini')
                ->required()
                ->options(fn () => \App\Models\QuestionBank::pluck('title', 'id'))
                ->searchable()
                ->preload()
                ->helperText('Satu Exam = satu Bank Soal (mis. "SKD CPNS Part 10"). Untuk menjual beberapa Part sekaligus, kumpulkan beberapa Exam terpisah ke dalam satu Package -- JANGAN gabung banyak bank ke satu Exam, karena passing grade & section per kategori jadi tidak bisa dihitung per Part.'),
            TextInput::make('title')
                ->required()
                ->maxLength(255),
            TextInput::make('duration_minutes')
                ->numeric()
                ->required()
                ->suffix('menit'),
            TextInput::make('passing_score')
                ->numeric()
                ->helperText('Kosongkan kalau tidak ada passing score total. Dipakai HANYA kalau "Wajib lulus semua bagian" di bawah TIDAK diaktifkan.'),
            Toggle::make('require_all_sections_pass')
                ->label('Wajib lulus semua bagian (section)')
                ->helperText('Aktifkan untuk exam bertipe CPNS: siswa harus mencapai skor minimal di SETIAP bagian (TWK/TIU/TKP dst). Kalau aktif, "Passing Score" total di atas diabaikan.')
                ->default(false),
            Toggle::make('uses_section_timers')
                ->label('Timer per bagian (TOEFL-style)')
                ->helperText('Aktifkan kalau tiap bagian (TWK/TIU/TKP dst) punya waktu SENDIRI-SENDIRI yang berjalan otomatis lanjut ke bagian berikutnya saat habis (mis. TOEFL). Kalau TIDAK aktif, exam pakai satu timer total seperti biasa (mis. CPNS). Wajib isi "Durasi (menit)" di tiap section kalau opsi ini diaktifkan.')
                ->default(false),
            Toggle::make('is_free_preview')
                ->label('Free preview (bisa diakses tanpa enrollment)')
                ->default(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable()->sortable(),
                TextColumn::make('bank_soal')
                    ->label('Bank Soal')
                    ->getStateUsing(fn (Exam $record) => $record->questions()
                        ->join('question_banks', 'questions.bank_id', '=', 'question_banks.id')
                        ->distinct()
                        ->pluck('question_banks.title')
                        ->implode(', '))
                    ->wrap(),
                TextColumn::make('duration_minutes')->suffix(' menit'),
                TextColumn::make('questions_count')->counts('questions')->label('Jumlah Soal'),
                IconColumn::make('is_free_preview')->label('Free Preview')->boolean(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getRelations(): array
    {
        return [
            SectionsRelationManager::class,
            QuestionsRelationManager::class,
            BatchesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExams::route('/'),
            'create' => Pages\CreateExam::route('/create'),
            'edit' => Pages\EditExam::route('/{record}/edit'),
        ];
    }
}

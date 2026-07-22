<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExamResource\Pages;
use App\Filament\Resources\ExamResource\RelationManagers\BatchesRelationManager;
use App\Filament\Resources\ExamResource\RelationManagers\QuestionsRelationManager;
use App\Filament\Resources\ExamResource\RelationManagers\SectionsRelationManager;
use App\Models\Exam;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
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
            Select::make('program_id')
                ->label('Program')
                ->required()
                ->options(fn () => \App\Models\Program::pluck('name', 'id'))
                ->searchable()
                ->preload()
                ->helperText('Exam ini akan berisi soal dari Bank Soal milik Program ini. Setelah Exam dibuat, tambahkan Bank Soal per kategori lewat tab "Bagian Ujian" -> "Attach Bank Soal".'),
            TextInput::make('title')
                ->required()
                ->maxLength(255),
            TextInput::make('duration_minutes')
                ->numeric()
                ->minValue(1)
                ->required()
                ->suffix('menit'),
            TextInput::make('passing_score')
                ->numeric()
                ->minValue(0)
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
                TextColumn::make('program.name')->label('Program'),
                TextColumn::make('bank_soal')
                    ->label('Bank Soal Terpasang')
                    ->getStateUsing(fn (Exam $record) => $record->sections()
                        ->with('questionBank')
                        ->get()
                        ->pluck('questionBank.title')
                        ->filter()
                        ->implode(', '))
                    ->wrap(),
                TextColumn::make('duration_minutes')->suffix(' menit'),
                TextColumn::make('questions_count')->counts('questions')->label('Jumlah Soal'),
                IconColumn::make('is_free_preview')->label('Free Preview')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                // BARU: cegah delete Exam kalau ada soal ter-attach lewat
                // Bagian Ujian (exam_sections) di bawahnya. Tanpa ini, Laravel
                // mencoba hapus exam_sections juga (cascade), lalu DB menolak
                // mentah-mentah lewat foreign key constraint exam_questions
                // (error 500) -- sekarang admin dapat pesan yang jelas.
                DeleteAction::make()
                    ->before(function (DeleteAction $action, Exam $record) {
                        $sectionIds = $record->sections()->pluck('id');

                        $questionCount = DB::table('exam_questions')
                            ->whereIn('exam_section_id', $sectionIds)
                            ->count();

                        if ($questionCount > 0) {
                            Notification::make()
                                ->title('Tidak bisa menghapus Exam')
                                ->body("Exam \"{$record->title}\" masih punya {$questionCount} soal ter-attach lewat Bagian Ujian-nya. Lepas soal-soal itu dari semua Bagian Ujian dulu sebelum menghapus Exam ini.")
                                ->danger()
                                ->send();

                            $action->halt();
                        }
                    }),
            ])
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

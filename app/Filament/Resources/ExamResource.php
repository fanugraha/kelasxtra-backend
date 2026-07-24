<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExamResource\Pages;
use App\Filament\Resources\ExamResource\RelationManagers\BatchesRelationManager;
use App\Filament\Resources\ExamResource\RelationManagers\QuestionsRelationManager;
use App\Filament\Resources\ExamResource\RelationManagers\SectionsRelationManager;
use App\Models\Exam;
use App\Models\Program;
use App\Models\Taxonomy;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
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
                ->options(fn () => Program::pluck('name', 'id'))
                ->searchable()
                ->preload()
                ->live()
                ->afterStateUpdated(fn ($set) => $set('focus_taxonomy_id', null))
                ->helperText('Exam ini akan berisi soal dari Bank Soal milik Program ini. Setelah Exam dibuat, tambahkan Bank Soal per kategori lewat tab "Bagian Ujian" -> "Attach Bank Soal".'),
            TextInput::make('title')
                ->required()
                ->maxLength(255),
            Select::make('focus_mode')
                ->label('Tipe Exam')
                ->options([
                    'all_program' => 'All Program',
                    'focus_topic' => 'Fokus 1 Topik',
                ])
                ->required()
                ->live()
                ->default('all_program')
                ->afterStateUpdated(fn ($set) => $set('focus_taxonomy_id', null))
                ->helperText('"All Program" -> bisa attach Bank Soal kategori/mapel apa saja dari Program ini. "Fokus 1 Topik" -> Exam ini cuma untuk 1 kategori/mapel tertentu (mis. khusus TIU), pilihan Bank Soal di bawah otomatis kefilter.'),
            Select::make('focus_taxonomy_id')
                ->label(fn (Get $get) => Program::find($get('program_id'))?->usesSubjectMode()
                    ? 'Topik Fokus (Mapel)'
                    : 'Topik Fokus (Kategori)')
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
                ->visible(fn (Get $get) => $get('focus_mode') === 'focus_topic')
                ->required(fn (Get $get) => $get('focus_mode') === 'focus_topic')
                ->dehydrateStateUsing(fn (Get $get, $state) => $get('focus_mode') === 'focus_topic' ? $state : null)
                ->helperText('Cuma nampilin kategori/mapel dari Program yang dipilih di atas.'),

            // --- Mode switch: tentukan dulu 2 keputusan ini, baru field angka
            // di bawahnya otomatis menyesuaikan (live + Get). ---
            Toggle::make('require_all_sections_pass')
                ->label('Wajib lulus semua bagian (section)')
                ->live()
                ->helperText('Aktifkan untuk exam bertipe CPNS: siswa harus mencapai skor minimal di SETIAP bagian (TWK/TIU/TKP dst). Kalau aktif, "Passing Score Total" di bawah disembunyikan dan tidak dipakai.')
                ->default(false),
            TextInput::make('passing_score')
                ->label('Passing Score Total')
                ->numeric()
                ->minValue(0)
                ->visible(fn (Get $get) => ! $get('require_all_sections_pass'))
                ->helperText('Skor minimal total untuk lulus exam ini. Kosongkan kalau tidak ada.'),

            Toggle::make('uses_section_timers')
                ->label('Timer per bagian (TOEFL-style)')
                ->live()
                ->helperText('Aktifkan kalau tiap bagian (TWK/TIU/TKP dst) punya waktu SENDIRI-SENDIRI yang berjalan otomatis lanjut ke bagian berikutnya saat habis (mis. TOEFL). Kalau TIDAK aktif, exam pakai satu timer total (mis. CPNS).')
                ->default(false),
            TextInput::make('duration_minutes')
                ->label('Durasi Total Exam')
                ->numeric()
                ->minValue(1)
                ->visible(fn (Get $get) => ! $get('uses_section_timers'))
                ->required(fn (Get $get) => ! $get('uses_section_timers'))
                ->suffix('menit')
                ->helperText('Durasi TOTAL untuk seluruh exam. Kalau "Timer per bagian" di atas AKTIF, field ini disembunyikan -- durasi diatur per bagian lewat tab "Bagian Ujian" SETELAH exam ini disimpan.'),

            Toggle::make('is_free_preview')
                ->label('Free preview (bisa diakses tanpa enrollment)')
                ->default(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('program.name')
                    ->label('Program')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('focus_status')
                    ->label('Fokus')
                    ->state(fn (Exam $record) => $record->focus_mode === 'focus_topic'
                        ? 'Fokus: ' . ($record->focusTaxonomy?->name ?? '-')
                        : 'All Program')
                    ->badge()
                    ->color(fn (Exam $record) => $record->focus_mode === 'focus_topic' ? 'warning' : 'success'),
                TextColumn::make('bank_soal')
                    ->label('Bank Soal Terpasang')
                    ->getStateUsing(fn (Exam $record) => $record->sections()
                        ->with('questionBank')
                        ->get()
                        ->pluck('questionBank.title')
                        ->filter()
                        ->values()
                        ->all())
                    ->badge()
                    ->color('gray')
                    ->wrap(),
                TextColumn::make('duration_minutes')
                    ->label('Durasi')
                    ->suffix(' menit')
                    ->alignCenter(),
                TextColumn::make('questions_count')
                    ->counts('questions')
                    ->label('Jumlah Soal')
                    ->alignCenter(),
                IconColumn::make('is_free_preview')
                    ->label('Free Preview')
                    ->boolean(),
            ])
            ->defaultSort('title')
            ->filters([
                SelectFilter::make('program_id')
                    ->label('Program')
                    ->relationship('program', 'name'),
                SelectFilter::make('focus_mode')
                    ->label('Tipe Exam')
                    ->options([
                        'all_program' => 'All Program',
                        'focus_topic' => 'Fokus 1 Topik',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
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

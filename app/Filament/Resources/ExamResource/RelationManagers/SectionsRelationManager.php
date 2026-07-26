<?php

namespace App\Filament\Resources\ExamResource\RelationManagers;

use App\Models\Question;
use App\Models\QuestionBank;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class SectionsRelationManager extends RelationManager
{
    protected static string $relationship = 'sections';

    protected static ?string $title = 'Bagian Ujian';

    protected static ?string $description = 'Catatan: kalau kamu baru saja mengubah "Timer per bagian" atau "Wajib lulus semua bagian" di form Exam di atas, klik "Save changes" DULU sebelum Attach Bank Soal -- field Durasi/Skor Minimal Lulus di bawah ini mengikuti pengaturan yang SUDAH TERSIMPAN, bukan yang baru dicentang.';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')
                ->label('Kode')
                ->required()
                ->maxLength(255),
            TextInput::make('name')
                ->label('Nama')
                ->required()
                ->maxLength(255),
            TextInput::make('order')
                ->label('Urutan')
                ->numeric()
                ->minValue(1)
                ->required()
                ->default(1),
            TextInput::make('min_passing_score')
                ->label('Skor Minimal Lulus')
                ->numeric()
                ->minValue(0)
                ->helperText('Kosongkan kalau bagian ini tidak punya passing score sendiri.')
                ->visible(fn () => (bool) $this->getOwnerRecord()->require_all_sections_pass),
            TextInput::make('max_score')
                ->label('Skor Maksimal')
                ->numeric()
                ->minValue(1)
                ->required(),
            TextInput::make('duration_minutes')
                ->label('Durasi')
                ->numeric()
                ->minValue(1)
                ->required(fn () => (bool) $this->getOwnerRecord()->uses_section_timers)
                ->suffix('menit')
                ->visible(fn () => (bool) $this->getOwnerRecord()->uses_section_timers),
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
                TextColumn::make('taxonomy_name')->label('Kategori/Mapel'),
                TextColumn::make('questionBank.title')->label('Bank Soal'),
                TextColumn::make('scoring_type')->badge(),
                TextColumn::make('duration_minutes')
                    ->suffix(' menit')
                    ->visible(fn () => (bool) $this->getOwnerRecord()->uses_section_timers),
                TextColumn::make('max_score')->label('Skor Maks'),
                IconColumn::make('is_locked_after_next')->label('Terkunci')->boolean(),
            ])
            ->headerActions([
                Action::make('attachBank')
                    ->label('Attach Bank Soal')
                    ->color('success')
                    ->schema([
                        Select::make('question_bank_id')
                            ->label('Bank Soal')
                            ->required()
                            ->live()
                            ->options(function () {
                                $exam = $this->getOwnerRecord();

                                return QuestionBank::where('program_id', $exam->program_id)
                                    ->whereNotNull('taxonomy_id')
                                    ->when(
                                        $exam->focus_mode === 'focus_topic' && $exam->focus_taxonomy_id,
                                        fn ($q) => $q->where('taxonomy_id', $exam->focus_taxonomy_id)
                                    )
                                    ->whereDoesntHave('examSections', fn ($q) => $q->where('exam_id', $exam->id))
                                    ->with('taxonomy')
                                    ->get()
                                    ->mapWithKeys(fn (QuestionBank $bank) => [
                                        $bank->id => $bank->title . ' (' . ($bank->taxonomy->name ?? '-') . ')',
                                    ]);
                            })
                            ->searchable()
                            // BARU: begitu Bank Soal dipilih, otomatis hitung
                            // Skor Maksimal dari total poin benar semua soal
                            // di bank itu, dan isi Skor Minimal Lulus dari
                            // Taxonomy::passing_grade bank tsb (kalau ada).
                            // Admin tidak perlu hitung/input manual lagi.
                            ->afterStateUpdated(function (?string $state, Set $set) {
                                if (blank($state)) {
                                    $set('max_score', null);
                                    $set('min_passing_score', null);

                                    return;
                                }

                                $bank = QuestionBank::with(['questions.options', 'taxonomy'])->find($state);

                                if (! $bank) {
                                    return;
                                }

                                $isTkp = str_contains($bank->taxonomy?->name ?? '', 'Karakteristik Pribadi');
                                $maxScore = $bank->questions->sum(function (Question $question) use ($isTkp) {
                                    if ($isTkp) {
                                        return $question->options->max('points') ?? 0;
                                    }
                                    return $question->pointCorrect();
                                });
                                $set('max_score', $maxScore);

                                $passingGrade = $bank->taxonomy?->passing_grade;

                                if ($passingGrade !== null) {
                                    $set('min_passing_score', $passingGrade);
                                }
                            }),
                        TextInput::make('max_score')
                            ->label('Skor Maksimal Bagian Ini (otomatis)')
                            ->numeric()
                            ->minValue(1)
                            ->required()
                            ->readOnly()
                            ->helperText('Dihitung otomatis dari total poin benar semua soal di Bank Soal yang dipilih (termasuk override per-soal kalau ada). Tidak perlu diisi manual -- pilih Bank Soal-nya dulu di atas.'),
                        TextInput::make('duration_minutes')
                            ->label('Durasi')
                            ->numeric()
                            ->minValue(1)
                            ->required(fn () => (bool) $this->getOwnerRecord()->uses_section_timers)
                            ->suffix('menit')
                            ->visible(fn () => (bool) $this->getOwnerRecord()->uses_section_timers),
                        TextInput::make('min_passing_score')
                            ->label('Skor Minimal Lulus')
                            ->numeric()
                            ->minValue(0)
                            ->helperText('Otomatis terisi dari Passing Grade Kategori/Mapel bank soal ini (kalau ada di Program). Tetap bisa diubah kalau exam ini butuh nilai berbeda.')
                            ->visible(fn () => (bool) $this->getOwnerRecord()->require_all_sections_pass),
                    ])
                    ->action(function (array $data) {
                        $exam = $this->getOwnerRecord();
                        $bank = QuestionBank::findOrFail($data['question_bank_id']);

                        try {
                            $section = $exam->attachBank($bank, [
                                'order' => ($exam->sections()->max('order') ?? 0) + 1,
                                'max_score' => $data['max_score'],
                                'duration_minutes' => $data['duration_minutes'] ?? null,
                                'min_passing_score' => $data['min_passing_score'] ?? null,
                            ]);

                            Notification::make()
                                ->title("Bank Soal \"{$bank->title}\" berhasil di-attach ke bagian \"{$section->name}\".")
                                ->success()
                                ->send();
                        } catch (\InvalidArgumentException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('detachBank')
                    ->label('Detach Bank Soal')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Lepas Bank Soal dari Bagian Ujian ini?')
                    ->modalDescription('Semua soal di bagian ini akan dilepas dari Exam, dan bagian ujian ini sendiri akan dihapus. Soal aslinya di Bank Soal TIDAK ikut terhapus -- ini cuma melepas keterkaitannya ke Exam ini.')
                    ->action(function ($record) {
                        $exam = $this->getOwnerRecord();
                        $sectionName = $record->name;

                        $exam->detachSection($record);

                        Notification::make()
                            ->title("Bagian \"{$sectionName}\" berhasil dilepas dari Exam.")
                            ->success()
                            ->send();
                    }),
                DeleteAction::make()
                    ->before(function (DeleteAction $action, $record) {
                        $questionCount = DB::table('exam_questions')
                            ->where('exam_section_id', $record->id)
                            ->count();

                        if ($questionCount > 0) {
                            Notification::make()
                                ->title('Tidak bisa menghapus Bagian Ujian')
                                ->body("Bagian \"{$record->name}\" masih punya {$questionCount} soal ter-attach. Lepas atau pindahkan soal-soal itu ke bagian lain dulu sebelum menghapus bagian ini.")
                                ->danger()
                                ->send();

                            $action->halt();
                        }
                    }),
            ]);
    }
}

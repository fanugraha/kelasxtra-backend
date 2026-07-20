<?php

namespace App\Filament\Resources\ExamResource\RelationManagers;

use App\Models\QuestionBank;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
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
                ->required()
                ->default(1),
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
                TextColumn::make('questionBank.title')->label('Bank Soal'),
                TextColumn::make('scoring_type')->badge(),
                TextColumn::make('duration_minutes')->suffix(' menit'),
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
                            ->options(function () {
                                $exam = $this->getOwnerRecord();

                                return QuestionBank::where('program_id', $exam->program_id)
                                    ->whereNotNull('category_id')
                                    ->whereDoesntHave('examSections', fn ($q) => $q->where('exam_id', $exam->id))
                                    ->with('category')
                                    ->get()
                                    ->mapWithKeys(fn (QuestionBank $bank) => [
                                        $bank->id => $bank->title . ' (' . ($bank->category->name ?? '-') . ')',
                                    ]);
                            })
                            ->searchable(),
                        TextInput::make('max_score')
                            ->label('Skor Maksimal Bagian Ini')
                            ->numeric()
                            ->required(),
                        TextInput::make('duration_minutes')
                            ->label('Durasi')
                            ->numeric()
                            ->required()
                            ->suffix('menit'),
                        TextInput::make('min_passing_score')
                            ->label('Skor Minimal Lulus')
                            ->numeric()
                            ->helperText('Kosongkan kalau bagian ini tidak punya passing score sendiri.'),
                    ])
                    ->action(function (array $data) {
                        $exam = $this->getOwnerRecord();
                        $bank = QuestionBank::findOrFail($data['question_bank_id']);

                        try {
                            $section = $exam->attachBank($bank, [
                                'order' => ($exam->sections()->max('order') ?? 0) + 1,
                                'max_score' => $data['max_score'],
                                'duration_minutes' => $data['duration_minutes'],
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
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}

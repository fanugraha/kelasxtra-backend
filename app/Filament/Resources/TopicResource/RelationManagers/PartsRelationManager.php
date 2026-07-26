<?php

namespace App\Filament\Resources\TopicResource\RelationManagers;

use App\Models\Question;
use App\Models\TopicUsedQuestion;
use App\Filament\Resources\ExamResource;
use App\Services\TopicPartGenerator;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PartsRelationManager extends RelationManager
{
    protected static string $relationship = 'exams';

    protected static ?string $title = 'Part Latihan';

    public function table(Table $table): Table
    {
        $topic = $this->getOwnerRecord();

        $totalCount = Question::where('topic_id', $topic->id)->count();
        $usedCount = TopicUsedQuestion::where('topic_id', $topic->id)->count();
        $remaining = $totalCount - $usedCount;

        return $table
            ->description(
                "Total soal topik ini: {$totalCount} · Sudah dipakai: {$usedCount} · Sisa stok: {$remaining}" .
                ($remaining < 10 ? ' — ⚠️ stok tinggal sedikit, pertimbangkan impor soal baru.' : '')
            )
            ->columns([
                TextColumn::make('part_number')->label('Part')->sortable(),
                TextColumn::make('title')->label('Judul'),
                TextColumn::make('questions_count')->counts('questions')->label('Jumlah Soal'),
                TextColumn::make('duration_minutes')->label('Durasi')->suffix(' menit'),
                TextColumn::make('created_at')->label('Dibuat')->dateTime('d M Y H:i'),
            ])
            ->defaultSort('part_number')
            ->headerActions([
                Action::make('tambahPart')
                    ->label('+ Tambah Part')
                    ->schema([
                        TextInput::make('jumlah_soal')
                            ->label('Jumlah Soal')
                            ->numeric()
                            ->default(10)
                            ->minValue(1)
                            ->required(),
                        TextInput::make('durasi_menit')
                            ->label('Durasi (menit)')
                            ->numeric()
                            ->minValue(1)
                            ->helperText('Kosongkan untuk otomatis (1 menit per soal, minimal 5 menit).'),
                    ])
                    ->action(function (array $data) {
                        try {
                            $exam = app(TopicPartGenerator::class)
                                ->generateNextPart(
                                    $this->getOwnerRecord(),
                                    (int) $data['jumlah_soal'],
                                    filled($data['durasi_menit'] ?? null) ? (int) $data['durasi_menit'] : null
                                );

                            Notification::make()
                                ->title("Part {$exam->part_number} berhasil dibuat")
                                ->success()
                                ->send();

                            // Angka ringkasan (Total/Sudah dipakai/Sisa stok) dihitung
                            // manual di table(), gak otomatis ke-refresh cuma karena
                            // action ini sukses -- paksa Filament rebuild table()-nya.
                            $this->resetTable();
                        } catch (\RuntimeException $e) {
                            Notification::make()
                                ->title('Gagal generate part')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->recordActions([
                \Filament\Actions\Action::make("kelolaSoal")
                    ->label("Kelola Soal")
                    ->icon("heroicon-o-pencil-square")
                    ->url(fn ($record) => ExamResource::getUrl("edit", ["record" => $record->id]))
                    ->openUrlInNewTab(),
            ]);
    }
}

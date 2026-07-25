<?php

namespace App\Filament\Resources\TopicResource\RelationManagers;

use App\Models\Question;
use App\Models\TopicUsedQuestion;
use App\Services\TopicPartGenerator;
use Filament\Actions\Action;
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
                TextColumn::make('created_at')->label('Dibuat')->dateTime('d M Y H:i'),
            ])
            ->defaultSort('part_number')
            ->headerActions([
                Action::make('tambahPart')
                    ->label('+ Tambah Part')
                    ->action(function () {
                        try {
                            $exam = app(TopicPartGenerator::class)
                                ->generateNextPart($this->getOwnerRecord());

                            Notification::make()
                                ->title("Part {$exam->part_number} berhasil dibuat")
                                ->success()
                                ->send();
                        } catch (\RuntimeException $e) {
                            Notification::make()
                                ->title('Gagal generate part')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ]);
    }
}

<?php

namespace App\Filament\Resources\ExamResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Read-only. Soal masuk ke exam ini HANYA lewat "Attach Bank Soal" di tab
 * Bagian Ujian (Exam::attachBank()) -- tidak ada lagi attach/import/edit
 * poin per soal di level Exam, karena satu Bank Soal selalu dipakai utuh
 * dan poinnya sudah ditentukan di level Bank Soal.
 */
class QuestionsRelationManager extends RelationManager
{
    protected static string $relationship = 'questions';

    protected static ?string $title = 'Soal dalam Exam Ini';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('question_text')
            ->columns([
                TextColumn::make('question_text')->label('Pertanyaan')->limit(60),
                TextColumn::make('bank.title')->label('Bank Soal'),
                TextColumn::make('type')->badge(),
                TextColumn::make('pivot.exam_section_id')
                    ->label('Bagian Ujian')
                    ->formatStateUsing(fn ($state) => $this->getOwnerRecord()->sections()->find($state)?->name ?? '-'),
            ]);
    }
}

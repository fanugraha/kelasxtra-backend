<?php

namespace App\Filament\Resources\ExamResource\RelationManagers;

use App\Filament\Imports\QuestionImporter;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ImportAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Menghubungkan soal ke exam ini lewat pivot exam_questions (kolom `points`,
 * `exam_section_id`). Soal itu sendiri dikelola terpisah di menu "Soal"
 * (QuestionResource) — di sini bisa Attach soal yang sudah ada, atau Import
 * CSV yang otomatis bikin soal baru DAN langsung merakitnya ke exam ini
 * (exam_id sudah otomatis fix ke exam yang sedang dibuka, tinggal pilih
 * Bank Soal tujuan dan Bagian Ujian saat import).
 */
class QuestionsRelationManager extends RelationManager
{
    protected static string $relationship = 'questions';

    protected static ?string $title = 'Soal dalam Exam Ini';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('exam_section_id')
                ->label('Bagian Ujian')
                ->options(fn () => $this->getOwnerRecord()->sections()->pluck('name', 'id'))
                ->searchable()
                ->helperText('Kosongkan kalau exam ini tidak memakai pembagian section.'),
            TextInput::make('points')
                ->label('Poin')
                ->numeric()
                ->default(1)
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('question_text')
            ->columns([
                TextColumn::make('question_text')->label('Pertanyaan')->limit(60),
                TextColumn::make('type')->badge(),
                TextColumn::make('pivot.points')->label('Poin'),
                TextColumn::make('pivot.exam_section_id')
                    ->label('Bagian Ujian')
                    ->formatStateUsing(fn ($state) => $this->getOwnerRecord()->sections()->find($state)?->name ?? '-'),
            ])
            ->headerActions([
                ImportAction::make()
                    ->label('Import Soal Baru')
                    ->importer(QuestionImporter::class)
                    ->options(fn () => ['exam_id' => $this->getOwnerRecord()->getKey()])
                    ->maxRows(1000)
                    ->chunkSize(50),
                AttachAction::make()
                    ->label('Attach Soal Existing')
                    ->recordSelectSearchColumns(['question_text'])
                    ->schema(fn (AttachAction $action) => [
                        $action->getRecordSelect(),
                        Select::make('exam_section_id')
                            ->label('Bagian Ujian')
                            ->options(fn () => $this->getOwnerRecord()->sections()->pluck('name', 'id'))
                            ->searchable()
                            ->helperText('Kosongkan kalau exam ini tidak memakai pembagian section.'),
                        TextInput::make('points')->label('Poin')->numeric()->default(1)->required(),
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                DetachAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DetachBulkAction::make()]),
            ]);
    }
}

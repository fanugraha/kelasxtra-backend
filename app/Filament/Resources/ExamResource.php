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
                ->label('Bank Utama (kategorisasi)')
                ->relationship('bank', 'title')
                ->required()
                ->searchable()
                ->preload()
                ->helperText('Dipakai untuk kategorisasi/label saja -- soal exam ini bisa berasal dari bank manapun lewat tab "Soal dalam Exam Ini" di bawah, tidak dibatasi oleh pilihan di sini.'),
            TextInput::make('title')
                ->required()
                ->maxLength(255),
            TextInput::make('duration_minutes')
                ->numeric()
                ->required()
                ->suffix('menit'),
            TextInput::make('passing_score')
                ->numeric()
                ->helperText('Kosongkan kalau tidak ada passing score.'),
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
                TextColumn::make('bank.title')->label('Bank Utama'),
                TextColumn::make('available_banks')
                    ->label('Bank yang Dipakai')
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

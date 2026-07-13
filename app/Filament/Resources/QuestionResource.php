<?php

namespace App\Filament\Resources;

use App\Filament\Resources\QuestionResource\Pages;
use App\Models\Question;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use App\Models\Program;
use Illuminate\Database\Eloquent\Builder;

/**
 * Menu lama, sekarang READ-ONLY.
 *
 * Alasan: form ini tidak punya field Kategori (category_id), jadi soal
 * yang dibuat lewat sini tidak akan muncul di tab kategori manapun pada
 * halaman Bank Soal (lihat insiden soal "lalala", 11 Juli 2026).
 *
 * Pengelolaan soal yang benar sekarang ada di:
 * Bank Soals -> Edit -> panel "Soal" (QuestionsRelationManager).
 *
 * Menu ini dipertahankan hanya untuk melihat/audit data lama, bukan
 * untuk membuat atau mengubah soal.
 */
class QuestionResource extends Resource
{
    protected static ?string $model = Question::class;

    protected static ?string $modelLabel = 'Soal (Arsip - Lihat saja)';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    protected static string|\UnitEnum|null $navigationGroup = 'Bank Soal & Ujian';

    protected static ?int $navigationSort = 3;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return true;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('bank_id')
                ->label('Bank Soal')
                ->relationship('bank', 'title')
                ->disabled(),
            Textarea::make('question_text')
                ->label('Pertanyaan')
                ->rows(4)
                ->disabled()
                ->columnSpanFull(),
            FileUpload::make('image_url')
                ->label('Gambar')
                ->image()
                ->directory('question-images')
                ->disabled()
                ->columnSpanFull(),
            Select::make('type')
                ->options([
                    'pg' => 'Pilihan Ganda',
                    'essay' => 'Essay',
                ])
                ->disabled(),
            Select::make('difficulty')
                ->options([
                    'mudah' => 'Mudah',
                    'sedang' => 'Sedang',
                    'sulit' => 'Sulit',
                ])
                ->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('question_text')->label('Pertanyaan')->limit(60)->searchable(),
                TextColumn::make('bank.title')->label('Bank Soal'),
                TextColumn::make('type')->badge(),
                TextColumn::make('difficulty')->badge(),
                TextColumn::make('options_count')->counts('options')->label('Jumlah Opsi'),
            ])
            ->filters([
                SelectFilter::make('program')
                    ->label('Program')
                    ->options(Program::pluck('name', 'id'))
                    ->query(function (Builder $query, array $data) {
                        if (blank($data['value'] ?? null)) {
                            return $query;
                        }

                        return $query->whereHas('bank', function (Builder $q) use ($data) {
                            $q->where('program_id', $data['value']);
                        });
                    }),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\QuestionResource\RelationManagers\OptionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListQuestions::route('/'),
            'view' => Pages\EditQuestion::route('/{record}'),
        ];
    }
}
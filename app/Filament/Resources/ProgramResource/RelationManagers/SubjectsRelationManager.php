<?php

namespace App\Filament\Resources\ProgramResource\RelationManagers;

use App\Models\QuestionBank;
use App\Models\Taxonomy;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SubjectsRelationManager extends RelationManager
{
    protected static string $relationship = 'subjects';

    protected static ?string $title = 'Mata Pelajaran';

    // Kebalikan dari CategoriesRelationManager: tab ini cuma muncul buat
    // Program mode 'subject'. Read-only karena Mapel dikelola terpisah di
    // menu "Mata Pelajaran", tab ini cuma buat lihat mapel apa saja yang
    // sudah dipakai di Program ini lewat Bank Soal.
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord->usesSubjectMode();
    }

    public function table(Table $table): Table
    {
        return $table
            // Override query relasi: tampilkan SEMUA Mata Pelajaran yang ada
            // (bukan cuma yang sudah punya Bank Soal di Program ini), supaya
            // admin bisa langsung lihat mapel mana yang belum digarap.
            ->query(fn (): Builder => Taxonomy::subjects())
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Mapel')
                    ->searchable(),
                TextColumn::make('jumlah_bank_soal')
                    ->label('Jumlah Bank Soal')
                    ->getStateUsing(fn ($record) => QuestionBank::where('program_id', $this->getOwnerRecord()->id)
                        ->where('taxonomy_id', $record->id)
                        ->count()),
            ])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}

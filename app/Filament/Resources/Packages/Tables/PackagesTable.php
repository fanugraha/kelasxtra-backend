<?php

namespace App\Filament\Resources\Packages\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PackagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('program.name')
                    ->label('Program')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('subject.name')
                    ->label('Mapel')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('type')
                    ->badge(),
                IconColumn::make('is_focus_topic')
                    ->label('Fokus Topik')
                    ->boolean(),
                TextColumn::make('price')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('discount_price')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('duration_days')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                ReplicateAction::make()
                    ->label('Duplikat')
                    ->excludeAttributes(['created_at', 'updated_at'])
                    ->beforeReplicaSaved(function ($replica) {
                        $replica->name = $replica->name . ' (Copy)';
                    })
                    ->after(function ($record, $replica) {
                        $replica->exams()->sync($record->exams()->pluck('exams.id'));
                    })
                    ->successNotificationTitle('Paket berhasil diduplikat, tinggal ubah exam-nya'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

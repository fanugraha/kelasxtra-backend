<?php

namespace App\Filament\Resources\Promos\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PromosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable(),
                TextColumn::make('discount_type')
                    ->badge(),
                TextColumn::make('discount_value')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('code')
                    ->searchable(),
                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
                TextColumn::make('total_quota')
                    ->label('Kuota')
                    ->placeholder('Tanpa batas')
                    ->sortable(),
                TextColumn::make('valid_from')
                    ->label('Mulai')
                    ->dateTime()
                    ->placeholder('Langsung aktif')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('valid_until')
                    ->label('Sampai')
                    ->date()
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
            ->filtersLayout(\Filament\Tables\Enums\FiltersLayout::Dropdown)
            ->deferFilters(false)
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Status Aktif'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->emptyStateHeading('Tidak ada Promo yang cocok')
            ->emptyStateDescription('Coba ubah atau hapus filter yang aktif.')
            ->emptyStateIcon('heroicon-o-tag')
            ->emptyStateActions([
                \Filament\Actions\Action::make('resetFilters')
                    ->label('Hapus Semua Filter')
                    ->color('gray')
                    ->action(fn ($livewire) => $livewire->resetTableFiltersForm()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
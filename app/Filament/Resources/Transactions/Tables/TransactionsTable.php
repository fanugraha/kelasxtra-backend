<?php

namespace App\Filament\Resources\Transactions\Tables;

use App\Filament\Resources\Transactions\TransactionResource;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Siswa')
                    ->searchable(),
                TextColumn::make('package.name')
                    ->label('Paket')
                    ->searchable(),
                TextColumn::make('midtrans_order_id')
                    ->label('Order ID')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('amount')
                    ->label('Jumlah')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('payment_method')
                    ->label('Metode')
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'success' => 'success',
                        'failed' => 'danger',
                        'expired' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('paid_at')
                    ->label('Dibayar')
                    ->dateTime('d M Y H:i')
                    ->placeholder('Belum dibayar')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filtersLayout(\Filament\Tables\Enums\FiltersLayout::Dropdown)
            ->deferFilters(false)
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'Pending',
                        'success' => 'Sukses',
                        'failed' => 'Gagal',
                        'expired' => 'Kedaluwarsa',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                TransactionResource::markSuccessAction(),
            ])
            ->emptyStateHeading('Tidak ada Transaksi yang cocok')
            ->emptyStateDescription('Coba ubah atau hapus filter yang aktif.')
            ->emptyStateIcon('heroicon-o-credit-card')
            ->emptyStateActions([
                \Filament\Actions\Action::make('resetFilters')
                    ->label('Hapus Semua Filter')
                    ->color('gray')
                    ->action(fn ($livewire) => $livewire->resetTableFiltersForm()),
            ])
            ->toolbarActions([]);
    }
}

<?php

namespace App\Filament\Resources\Enrollments\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EnrollmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Siswa')
                    ->searchable(),
                TextColumn::make('classRoom.name')
                    ->label('Kelas')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('package.name')
                    ->label('Paket')
                    ->searchable(),
                TextColumn::make('transaction.midtrans_order_id')
                    ->label('Transaksi')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'active' => 'success',
                        'expired' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('start_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('end_date')
                    ->date()
                    ->sortable()
                    ->placeholder('Tak terbatas'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'Pending',
                        'active' => 'Aktif',
                        'expired' => 'Kedaluwarsa',
                    ]),
            ])
            ->recordActions([
                Action::make('activate')
                    ->label('Aktifkan')
                    ->icon('heroicon-m-check-circle')
                    ->color('success')
                    ->visible(fn ($record): bool => $record->status !== 'active')
                    ->requiresConfirmation()
                    ->modalDescription('Enrollment ini akan diaktifkan sehingga siswa dapat mengakses kelas.')
                    ->action(function ($record) {
                        $record->update([
                            'status' => 'active',
                            'start_date' => $record->start_date ?? now(),
                        ]);

                        Notification::make()
                            ->title('Enrollment diaktifkan')
                            ->success()
                            ->send();
                    }),
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

<?php

namespace App\Filament\Resources\Transactions;

use App\Filament\Resources\Transactions\Pages\EditTransaction;
use App\Filament\Resources\Transactions\Pages\ListTransactions;
use App\Filament\Resources\Transactions\Pages\ViewTransaction;
use App\Filament\Resources\Transactions\RelationManagers\LogsRelationManager;
use App\Filament\Resources\Transactions\Schemas\TransactionForm;
use App\Filament\Resources\Transactions\Schemas\TransactionInfolist;
use App\Filament\Resources\Transactions\Tables\TransactionsTable;
use App\Models\Transaction;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static ?string $modelLabel = 'Transaksi';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|\UnitEnum|null $navigationGroup = 'Produk & Promosi';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return TransactionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TransactionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TransactionsTable::configure($table);
    }

    // Cukup ubah status transaksi — sinkronisasi ke enrollment (aktifkan/cabut)
    // sudah otomatis ditangani di Transaction::booted().
    public static function markSuccessAction(): Action
    {
        return Action::make('markSuccess')
            ->label('Tandai Sukses & Aktifkan Enrollment')
            ->icon('heroicon-m-check-circle')
            ->color('success')
            ->visible(fn (Transaction $record): bool => $record->status !== 'success')
            ->requiresConfirmation()
            ->modalDescription('Transaksi akan ditandai sukses, dan enrollment terkait (jika ada) akan diaktifkan otomatis. Gunakan ini kalau siswa sudah bayar tapi status belum ter-update otomatis.')
            ->action(function (Transaction $record) {
                $record->update([
                    'status' => 'success',
                    'paid_at' => $record->paid_at ?? now(),
                ]);

                $hasEnrollment = $record->fresh()->enrollment !== null;

                Notification::make()
                    ->title('Transaksi ditandai sukses')
                    ->body($hasEnrollment
                        ? 'Enrollment terkait sudah diaktifkan.'
                        : 'Tidak ditemukan enrollment terkait — cek manual di menu Enrollments.')
                    ->success()
                    ->send();
            });
    }

    public static function getRelations(): array
    {
        return [
            LogsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTransactions::route('/'),
            'view' => ViewTransaction::route('/{record}'),
            'edit' => EditTransaction::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('status', 'pending')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}

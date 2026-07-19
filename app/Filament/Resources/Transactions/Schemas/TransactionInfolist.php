<?php

namespace App\Filament\Resources\Transactions\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TransactionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Transaksi')
                ->columns(2)
                ->schema([
                    TextEntry::make('user.name')->label('Siswa'),
                    TextEntry::make('package.name')->label('Paket'),
                    TextEntry::make('midtrans_order_id')->label('Order ID')->copyable(),
                    TextEntry::make('payment_method')->label('Metode Bayar')->placeholder('—'),
                    TextEntry::make('amount')->label('Jumlah')->money('IDR'),
                    TextEntry::make('discount_amount')->label('Diskon')->money('IDR')->placeholder('—'),
                    TextEntry::make('status')
                        ->label('Status')
                        ->badge()
                        ->color(fn (string $state): string => match ($state) {
                            'pending' => 'warning',
                            'success' => 'success',
                            'failed' => 'danger',
                            'expired' => 'gray',
                            default => 'gray',
                        }),
                    TextEntry::make('paid_at')->label('Dibayar Pada')->dateTime('d M Y H:i')->placeholder('Belum dibayar'),
                ]),
            Section::make('Enrollment Terkait')
                ->columns(2)
                ->schema([
                    TextEntry::make('enrollment.classRoom.name')->label('Kelas')->placeholder('—'),
                    TextEntry::make('enrollment.status')
                        ->label('Status Enrollment')
                        ->badge()
                        ->placeholder('Belum ada enrollment')
                        ->color(fn (?string $state): string => match ($state) {
                            'pending' => 'warning',
                            'active' => 'success',
                            'expired' => 'gray',
                            default => 'gray',
                        }),
                ]),
        ]);
    }
}

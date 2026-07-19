<?php

namespace App\Filament\Resources\Transactions\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TransactionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Info Transaksi (tidak bisa diubah)')
                ->columns(2)
                ->schema([
                    TextInput::make('user.name')
                        ->label('Siswa')
                        ->disabled()
                        ->dehydrated(false),
                    TextInput::make('midtrans_order_id')
                        ->label('Order ID')
                        ->disabled()
                        ->dehydrated(false),
                    TextInput::make('amount')
                        ->label('Jumlah')
                        ->disabled()
                        ->dehydrated(false)
                        ->prefix('IDR'),
                    TextInput::make('payment_method')
                        ->label('Metode Bayar')
                        ->disabled()
                        ->dehydrated(false),
                ]),
            Section::make('Ubah Status')
                ->columns(2)
                ->schema([
                    Select::make('status')
                        ->label('Status')
                        ->options([
                            'pending' => 'Pending',
                            'success' => 'Sukses',
                            'failed' => 'Gagal',
                            'expired' => 'Kedaluwarsa',
                        ])
                        ->required(),
                    DateTimePicker::make('paid_at')
                        ->label('Dibayar Pada')
                        ->helperText('Isi/kosongkan sesuai status. Kosongkan kalau belum dibayar.'),
                ]),
        ]);
    }
}

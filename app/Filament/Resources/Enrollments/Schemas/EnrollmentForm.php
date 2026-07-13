<?php

namespace App\Filament\Resources\Enrollments\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class EnrollmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->label('Siswa')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('package_id')
                    ->label('Paket')
                    ->relationship('package', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('class_id')
                    ->label('Kelas')
                    ->relationship('classRoom', 'name')
                    ->searchable()
                    ->preload()
                    ->helperText('Opsional — kosongkan kalau paket tipe latihan_soal (tanpa kelas tatap muka).'),
                Select::make('transaction_id')
                    ->label('Transaksi')
                    ->relationship('transaction', 'midtrans_order_id')
                    ->searchable()
                    ->preload(),
                Select::make('status')
                    ->options(['pending' => 'Pending', 'active' => 'Active', 'expired' => 'Expired'])
                    ->default('pending')
                    ->required(),
                DatePicker::make('start_date'),
                DatePicker::make('end_date')
                    ->helperText('Kosongkan untuk durasi tak terbatas.'),
            ]);
    }
}
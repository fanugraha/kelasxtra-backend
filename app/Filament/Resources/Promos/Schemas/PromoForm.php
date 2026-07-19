<?php

namespace App\Filament\Resources\Promos\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Fieldset;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PromoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Dasar')
                    ->columns(2)
                    ->components([
                        TextInput::make('title')
                            ->required()
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->columnSpanFull(),
                        Textarea::make('terms')
                            ->label('Syarat & Ketentuan')
                            ->helperText('Teks bebas, ditampilkan ke user di halaman promo. Kosongkan kalau tidak ada S&K khusus.')
                            ->columnSpanFull(),
                        Select::make('discount_type')
                            ->options(['percentage' => 'Percentage', 'fixed' => 'Fixed'])
                            ->required()
                            ->live(),
                        TextInput::make('discount_value')
                            ->required()
                            ->numeric(),
                        TextInput::make('code')
                            ->required()
                            ->unique(ignoreRecord: true),
                        TextInput::make('max_discount_amount')
                            ->label('Maksimal Potongan (Rp)')
                            ->numeric()
                            ->helperText('Wajib diisi kalau tipe diskon Percentage, supaya potongan tidak "meledak" di paket mahal. Kosongkan kalau tidak ingin dibatasi.')
                            ->visible(fn ($get) => $get('discount_type') === 'percentage'),
                    ]),

                Section::make('Jadwal & Status')
                    ->columns(3)
                    ->components([
                        DateTimePicker::make('valid_from')
                            ->label('Mulai Berlaku')
                            ->helperText('Kosongkan kalau langsung aktif begitu disimpan.'),

                        Toggle::make('unlimited_time')
                            ->label('Tanpa Batas Waktu')
                            ->live()
                            ->dehydrated()
                            ->helperText('Promo berlaku terus sampai dimatikan manual.')
                            ->afterStateHydrated(function (Toggle $component, $record) {
                                $component->state($record && is_null($record->valid_until));
                            }),

                        DatePicker::make('valid_until')
                            ->label('Berlaku Sampai')
                            ->required(fn (Get $get) => ! $get('unlimited_time'))
                            ->visible(fn (Get $get) => ! $get('unlimited_time')),

                        Toggle::make('is_active')
                            ->label('Aktifkan Promo')
                            ->default(true)
                            ->helperText('Tombol darurat: matikan tanpa hapus data.'),
                    ]),

                Section::make('Aturan Pemakaian')
                    ->description('Kuota Total & Limit per Akun sama-sama soal ANGKA, tapi beda cakupan — lihat penjelasan tiap kolom.')
                    ->components([
                        Placeholder::make('usage_info')
                            ->label('Status Pemakaian')
                            ->content(function ($record) {
                                if (! $record) {
                                    return 'Info pemakaian muncul setelah promo disimpan.';
                                }

                                $used = \App\Models\Transaction::where('promo_id', $record->id)
                                    ->where('status', 'success')
                                    ->count();

                                $quotaText = $record->total_quota
                                    ? "dari kuota {$record->total_quota}"
                                    : '(tanpa batas kuota)';

                                return "Sudah dipakai {$used} kali {$quotaText}.";
                            })
                            ->visible(fn ($record) => $record !== null)
                            ->columnSpanFull(),

                        Fieldset::make('Batas Kuota')
                            ->columns(2)
                            ->components([
                                TextInput::make('total_quota')
                                    ->label('Kuota Total')
                                    ->numeric()
                                    ->helperText('Berapa kali promo ini boleh dipakai oleh SEMUA orang digabung. Kosongkan = tanpa batas.'),
                                TextInput::make('usage_limit_per_user')
                                    ->label('Limit per Akun')
                                    ->numeric()
                                    ->helperText('Berapa kali 1 akun yang SAMA boleh pakai promo ini. Kosongkan = tanpa batas.'),
                            ]),

                        Fieldset::make('Target & Cakupan')
                            ->columns(2)
                            ->components([
                                Toggle::make('new_user_only')
                                    ->label('Khusus Siswa Baru')
                                    ->helperText('Hanya berlaku untuk akun yang belum pernah transaksi sukses.'),
                                Select::make('applicable_package_id')
                                    ->label('Khusus Paket Tertentu')
                                    ->relationship('applicablePackage', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->helperText('Kosongkan supaya promo berlaku untuk semua paket.'),
                            ]),
                    ]),
            ]);
    }
}

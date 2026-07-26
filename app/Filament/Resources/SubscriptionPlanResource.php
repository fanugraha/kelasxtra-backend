<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SubscriptionPlanResource\Pages;
use App\Models\SubscriptionPlan;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Support\RawJs;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SubscriptionPlanResource extends Resource
{
    protected static ?string $model = SubscriptionPlan::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|\UnitEnum|null $navigationGroup = 'Produk & Promosi';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Langganan';

    protected static ?string $modelLabel = 'Plan Langganan';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('Informasi Dasar')
                ->description('Nama dan deskripsi yang tampil di card langganan.')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Nama Plan')
                        ->required()
                        ->maxLength(255)
                        ->helperText('Misalnya "Langganan CPNS" atau "Langganan 3 Program".'),

                    TextInput::make('tagline')
                        ->label('Tagline / Badge')
                        ->maxLength(60)
                        ->helperText('Badge singkat di card, mis. "Paling Hemat" atau "Semua Program". Maks. 60 karakter.'),

                    Textarea::make('description')
                        ->label('Deskripsi')
                        ->rows(2)
                        ->maxLength(500)
                        ->columnSpanFull()
                        ->helperText('Penjelasan singkat 1-2 kalimat di bawah nama plan.'),
                ]),

            Section::make('Fitur & Benefit')
                ->description('Daftar fitur yang ditampilkan sebagai centang di card langganan.')
                ->schema([
                    Repeater::make('features')
                        ->label('')
                        ->simple(
                            TextInput::make('feature')
                                ->required()
                                ->maxLength(120)
                                ->placeholder('Mis. "Akses semua try out SKD CPNS"')
                        )
                        ->addActionLabel('+ Tambah Fitur')
                        ->reorderableWithButtons()
                        ->defaultItems(0)
                        ->helperText('Klik "+ Tambah Fitur" untuk menambah baris. Gunakan tombol ↑↓ pada tiap baris untuk mengatur urutan tampil.'),
                ]),

            Section::make('Cakupan & Durasi')
                ->description('Tentukan apakah plan ini untuk 1 program tetap, atau siswa bebas pilih beberapa program saat checkout.')
                ->columns(2)
                ->schema([
                    // Field virtual -- tidak disimpan ke database (dehydrated: false).
                    // Cuma dipakai untuk toggle tampilan program_id vs program_slot_count,
                    // dan untuk mengosongkan field yang tidak relevan saat berpindah mode.
                    Radio::make('coverage_type')
                        ->label('Cakupan Program')
                        ->options([
                            'single' => 'Fix ke 1 Program',
                            'multi' => 'Pilih N Program saat checkout (multi-select)',
                        ])
                        ->descriptions([
                            'single' => 'Plan ini hanya memberi akses ke satu program tertentu yang dipilih di bawah.',
                            'multi' => 'Siswa bebas memilih sejumlah program sendiri saat proses checkout.',
                        ])
                        ->default('single')
                        ->live()
                        ->dehydrated(false)
                        ->columnSpanFull()
                        ->afterStateHydrated(function (Radio $component, $record) {
                            if ($record) {
                                $component->state($record->program_slot_count ? 'multi' : 'single');
                            }
                        })
                        ->afterStateUpdated(function (string $state, Set $set) {
                            if ($state === 'single') {
                                $set('program_slot_count', null);
                            } else {
                                $set('program_id', null);
                            }
                        })
                        ->required(),

                    Select::make('program_id')
                        ->label('Program')
                        ->relationship('program', 'name')
                        ->searchable()
                        ->preload()
                        ->visible(fn (Get $get) => $get('coverage_type') === 'single')
                        ->required(fn (Get $get) => $get('coverage_type') === 'single'),

                    TextInput::make('program_slot_count')
                        ->label('Jumlah Program yang Wajib Dipilih')
                        ->numeric()
                        ->minValue(2)
                        ->visible(fn (Get $get) => $get('coverage_type') === 'multi')
                        ->required(fn (Get $get) => $get('coverage_type') === 'multi')
                        ->helperText('Siswa wajib memilih tepat sejumlah ini program saat checkout.'),

                    TextInput::make('duration_days')
                        ->label('Durasi (hari)')
                        ->numeric()
                        ->required()
                        ->default(30)
                        ->suffix('hari')
                        ->helperText('Lama akses langganan sejak aktif.'),
                ]),

            Section::make('Harga & Status')
                ->columns(2)
                ->schema([
                    TextInput::make('price')
                        ->label('Harga')
                        ->required()
                        ->numeric()
                        ->prefix('Rp')
                        ->mask(RawJs::make('$money($input, ".", ",", 0)'))
                        ->stripCharacters('.')
                        ->helperText('Otomatis diformat ribuan saat mengetik, contoh: 150.000.'),

                    Toggle::make('is_featured')
                        ->label('Highlight sebagai "Paling Populer"')
                        ->inline(false)
                        ->helperText('Hanya 1 plan sebaiknya di-highlight supaya pilihan tetap jelas.'),

                    Toggle::make('is_active')
                        ->label('Aktif')
                        ->default(true)
                        ->inline(false)
                        ->helperText('Kalau nonaktif, plan ini tidak muncul di halaman pembelian.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama Plan')->searchable()->sortable(),
                TextColumn::make('program.name')
                    ->label('Program')
                    ->placeholder('Multi-program')
                    ->badge(),
                TextColumn::make('program_slot_count')
                    ->label('Slot')
                    ->placeholder('-')
                    ->formatStateUsing(fn ($state) => $state ? "{$state} program" : null),
                TextColumn::make('duration_days')
                    ->label('Durasi')
                    ->formatStateUsing(fn ($state) => "{$state} hari")
                    ->sortable(),
                TextColumn::make('price')
                    ->label('Harga')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('subscriptions_count')
                    ->counts('subscriptions')
                    ->label('Jumlah Langganan Aktif'),
                IconColumn::make('is_active')->boolean(),
                IconColumn::make('is_featured')->label('Populer')->boolean(),
                TextColumn::make('created_at')->dateTime('d M Y')->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubscriptionPlans::route('/'),
            'create' => Pages\CreateSubscriptionPlan::route('/create'),
            'edit' => Pages\EditSubscriptionPlan::route('/{record}/edit'),
        ];
    }
}

<?php

namespace App\Filament\Resources\Packages\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TagsInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Forms\Components\FileUpload;
use Illuminate\Support\Facades\Storage;
use App\Models\Exam;

class PackageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('program_id')
                    ->label('Program')
                    ->relationship('program', 'name')
                    ->searchable()
                    ->preload()
                    ->requiredWithout('subject_id')
                    ->live()
                    ->helperText('Isi ini kalau paket terikat ke program tryout (CPNS, TOEFL, dll).'),
                Select::make('subject_id')
                    ->label('Mata Pelajaran')
                    ->relationship('subject', 'name')
                    ->searchable()
                    ->preload()
                    ->requiredWithout('program_id')
                    ->live()
                    ->helperText('Isi ini kalau paket berupa latihan/bimbel per-mapel (Matematika, Fisika, dll).'),
                TextInput::make('name')
                    ->required(),
                Select::make('type')
                    ->options([
                        'privat' => 'Privat',
                        'group' => 'Group',
                        'latihan_soal' => 'Latihan soal',
                        'reguler' => 'Reguler',
                    ])
                    ->required()
                    ->live(),
                Toggle::make('is_focus_topic')
                    ->label('Paket Fokus 1 Topik')
                    ->helperText('Aktifkan kalau paket ini jual latihan soal 1 kategori saja (mis. khusus TWK/TIU/TKP), bukan gabungan semua topik. Akan ditampilkan di section "Latihan Fokus" di Beranda, terpisah dari paket try out lengkap.')
                    ->default(false),
                Select::make('exams')
                    ->label('Exam/Ujian yang Dijual')
                    ->relationship('exams', 'title')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->visible(fn (Get $get) => $get('type') === 'latihan_soal')
                    ->required(fn (Get $get) => $get('type') === 'latihan_soal')
                    ->options(function (Get $get) {
                        $programId = $get('program_id');
                        $subjectId = $get('subject_id');

                        return Exam::query()
                            ->with('bank')
                            ->when($programId, fn ($q) => $q->whereHas('bank', fn ($b) => $b->where('program_id', $programId)))
                            ->when($subjectId, fn ($q) => $q->whereHas('bank', fn ($b) => $b->where('subject_id', $subjectId)))
                            ->get()
                            ->mapWithKeys(fn ($exam) => [$exam->id => $exam->title . ' (' . $exam->bank->title . ')']);
                    })
                    ->helperText('Pilih exam/ujian mana saja yang akan dibuka aksesnya untuk siswa yang membeli paket ini. Exam yang tidak dipilih di sini TIDAK akan bisa diakses meski satu bank soal.'),
                TextInput::make('price')
                    ->required()
                    ->numeric()
                    ->prefix('Rp'),
                TextInput::make('discount_price')
                    ->numeric()
                    ->prefix('Rp'),
                Toggle::make('is_lifetime')
                    ->label('Akses Selamanya (Lifetime)')
                    ->live()
                    ->dehydrated(false)
                    ->afterStateHydrated(function (Toggle $component, $record) {
                        if ($record) {
                            $component->state(blank($record->duration_days));
                        }
                    })
                    ->helperText('Aktifkan jika paket ini tidak punya batas waktu akses (tidak akan expired).'),
                TextInput::make('duration_days')
                    ->label('Durasi Akses (hari)')
                    ->numeric()
                    ->minValue(1)
                    ->visible(fn (Get $get) => ! $get('is_lifetime'))
                    ->required(fn (Get $get) => ! $get('is_lifetime'))
                    ->dehydrateStateUsing(fn (Get $get, $state) => $get('is_lifetime') ? null : $state)
                    ->helperText('Kosongkan / aktifkan toggle di atas untuk akses selamanya.'),
                TagsInput::make('features')
                    ->label('Fitur Paket')
                    ->placeholder('Ketik lalu Enter')
                    ->helperText('Contoh: "Soal berbasis HOTS", "Mencakup TWK, TIU, dan TKP". Muncul sebagai bullet di card & halaman detail.')
                    ->columnSpanFull(),
                TagsInput::make('materi')
                    ->label('Daftar Materi')
                    ->placeholder('Ketik lalu Enter')
                    ->helperText('Muncul sebagai grid materi di halaman detail paket, dan sebagai jumlah materi ("12 Materi") di card.')
                    ->columnSpanFull(),
                Textarea::make('description')
                    ->columnSpanFull(),
                FileUpload::make('banner_image_url')
                    ->label('Banner Paket')
                    ->image()
                    ->imageEditor()
                    ->disk('public')
                    ->directory('packages/banners')
                    ->visibility('public')
                    ->maxSize(2048)
                    ->columnSpanFull()
                    ->helperText('Gambar banner untuk card paket.')
                    ->afterStateHydrated(function (FileUpload $component, $state) {
                        if ($state && str_starts_with($state, 'http')) {
                            $path = parse_url($state, PHP_URL_PATH);
                            $relative = preg_replace('#^/storage/#', '', $path);
                            $component->state($relative);
                        }
                    })
                    ->dehydrateStateUsing(function ($state) {
                        if (! $state) {
                            return null;
                        }
                        $path = is_array($state) ? reset($state) : $state;
                        return Storage::disk('public')->url($path);
                    }),
            ]);
    }
}

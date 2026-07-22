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
use App\Models\Program;
use App\Models\Taxonomy;

class PackageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('program_id')
                    ->label('Program')
                    ->relationship('program', 'name')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function (Get $get, $set) {
                        $set('taxonomy_id', null);
                        $set('focus_taxonomy_id', null);
                    })
                    ->helperText('Program yang paket ini terikat (CPNS, TOEFL, dll). Wajib diisi.'),
                // Mata Pelajaran umum untuk paket. Cuma relevan buat Program
                // mode Mapel (mis. paket bimbel per-mapel Matematika, Fisika).
                Select::make('taxonomy_id')
                    ->label('Mata Pelajaran')
                    ->options(fn (Get $get) => Taxonomy::subjects()->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->visible(fn (Get $get) => Program::find($get('program_id'))?->usesSubjectMode() ?? false)
                    ->helperText('Isi kalau paket ini fokus ke satu Mapel tertentu (mis. Matematika, Fisika).'),
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
                    ->live()
                    ->visible(fn (Get $get) => $get('type') === 'latihan_soal')
                    ->dehydrateStateUsing(fn (Get $get, $state) => $get('type') === 'latihan_soal' ? $state : false)
                    ->helperText('Aktifkan kalau paket ini jual latihan soal 1 topik saja (mis. khusus TWK, atau khusus Matematika), bukan gabungan semua topik. Paket seperti ini akan dikelompokkan terpisah (section "Latihan Fokus") di halaman depan, beda dari paket try out lengkap yang jual semua topik sekaligus. Hanya tersedia untuk tipe paket "Latihan soal".')
                    ->default(false),
                // Topik Fokus: satu dropdown ini menentukan pengelompokan paket
                // di halaman depan untuk paket "Latihan Fokus" (is_focus_topic).
                // Isinya Kategori (kalau Program mode Kategori, mis. TWK) atau
                // Mapel (kalau Program mode Mapel, mis. Matematika).
                Select::make('focus_taxonomy_id')
                    ->label(fn (Get $get) => Program::find($get('program_id'))?->usesSubjectMode()
                        ? 'Topik Fokus (Mapel)'
                        : 'Topik Fokus (Kategori)')
                    ->searchable()
                    ->preload()
                    ->live()
                    ->visible(fn (Get $get) => $get('type') === 'latihan_soal' && $get('is_focus_topic'))
                    ->required(fn (Get $get) => $get('type') === 'latihan_soal' && $get('is_focus_topic'))
                    ->options(function (Get $get) {
                        $program = Program::find($get('program_id'));
                        if (! $program) {
                            return [];
                        }
                        return $program->usesSubjectMode()
                            ? Taxonomy::subjects()->pluck('name', 'id')
                            : Taxonomy::categories()->where('program_id', $program->id)->pluck('name', 'id');
                    })
                    ->helperText('Pilih topik yang jadi fokus paket ini. Menentukan pengelompokan paket ini di halaman depan (mis. dikelompokkan sebagai "Latihan TWK" atau "Latihan Matematika"). Hanya nampilin topik dari Program yang dipilih di atas.'),
                Select::make('exams')
                    ->label('Exam/Ujian yang Dijual')
                    ->relationship('exams', 'title')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->live()
                    ->visible(fn (Get $get) => $get('type') === 'latihan_soal')
                    ->required(fn (Get $get) => $get('type') === 'latihan_soal')
                    ->options(function (Get $get) {
                        $programId = $get('program_id');
                        $taxonomyId = $get('taxonomy_id');
                        $isFocusTopic = $get('is_focus_topic');
                        $focusTaxonomyId = $get('focus_taxonomy_id');

                        return Exam::query()
                            ->with('sections.questionBank')
                            ->when($programId, fn ($q) => $q->where('program_id', $programId))
                            ->when($taxonomyId, fn ($q) => $q->whereHas('sections.questionBank', fn ($b) => $b->where('taxonomy_id', $taxonomyId)))
                            // Fokus 1 Topik: exam harus 1-topik yang section-nya
                            // cocok Topik Fokus -- jangan sampai admin masukkan
                            // exam gabungan.
                            ->when($isFocusTopic && $focusTaxonomyId, fn ($q) => $q
                                ->whereHas('sections', fn ($s) => $s->where('taxonomy_id', $focusTaxonomyId))
                                ->whereDoesntHave('sections', fn ($s) => $s->where('taxonomy_id', '!=', $focusTaxonomyId)->orWhereNull('taxonomy_id')))
                            ->get()
                            ->mapWithKeys(fn ($exam) => [
                                $exam->id => $exam->title . ' (' . $exam->sections
                                    ->pluck('questionBank.title')
                                    ->filter()
                                    ->unique()
                                    ->implode(', ') . ')',
                            ]);
                    })
                    ->helperText(function (Get $get) {
                        $default = 'Pilih exam/ujian mana saja yang akan dibuka aksesnya untuk siswa yang membeli paket ini. Exam yang tidak dipilih di sini TIDAK akan bisa diakses meski satu bank soal.';

                        if ($get('is_focus_topic')) {
                            return $default . ' Daftar di atas sudah difilter: hanya exam 1-topik yang sesuai Topik Fokus yang muncul.';
                        }

                        $selectedIds = $get('exams') ?? [];
                        if (empty($selectedIds)) {
                            return $default;
                        }

                        $withoutFullPass = Exam::whereIn('id', $selectedIds)
                            ->where('require_all_sections_pass', false)
                            ->pluck('title');

                        if ($withoutFullPass->isNotEmpty()) {
                            return $default . ' PERINGATAN: exam berikut tidak mewajibkan "lulus semua bagian" -- '
                                . $withoutFullPass->implode(', ')
                                . '. Kalau paket ini try out gabungan, pertimbangkan aktifkan pengaturan itu di masing-masing Exam supaya penilaian konsisten.';
                        }

                        return $default;
                    }),
                TextInput::make('price')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->prefix('Rp'),
                TextInput::make('discount_price')
                    ->numeric()
                    ->minValue(0)
                    ->lt('price')
                    ->prefix('Rp')
                    ->helperText('Harus lebih kecil dari Price kalau diisi.'),
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

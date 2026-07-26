<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TopicResource\Pages;
use App\Models\Program;
use App\Models\Topic;
use App\Services\TopicPartGenerator;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TopicResource extends Resource
{
    protected static ?string $model = Topic::class;

    protected static ?string $modelLabel = 'Topik';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|\UnitEnum|null $navigationGroup = 'Bank Soal & Ujian';

    protected static ?int $navigationSort = 2;

    // Topic cuma boleh nempel ke Taxonomy tipe 'category' (mis. TWK/TIU/TKP),
    // bukan 'subject' -- makanya select taxonomy_id di-scope ke categories().
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('taxonomy_id')
                ->label('Kategori')
                ->relationship('taxonomy', 'name', fn ($query) => $query->categories())
                ->searchable()
                ->preload()
                ->required(),
            TextInput::make('code')->required()->maxLength(255),
            TextInput::make('name')->required()->maxLength(255),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            // Skalabilitas: sebelumnya "Sisa Stok" dan "Part Dibuat" dihitung
            // dengan query terpisah PER BARIS (N+1) -- baik untuk 14 topik,
            // tapi lambat kalau nanti ratusan topik lintas banyak Program
            // (CPNS/BUMN/UTBK/Umum/Sekolah). withCount() di sini menggabungkan
            // semuanya jadi query tunggal untuk SEMUA baris sekaligus.
            ->modifyQueryUsing(fn ($query) => $query->withCount([
                'questions',
                'usedQuestions',
                'exams as parts_count' => fn ($q) => $q->whereNotNull('part_number'),
            ]))
            ->columns([
                TextColumn::make('taxonomy.program.name')
                    ->label('Program')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('taxonomy.name')->label('Kategori')->searchable()->sortable(),
                TextColumn::make('code')->searchable()->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('questions_count')
                    ->label('Jumlah Soal')
                    ->badge()
                    ->color(fn (int $state): string => $state === 0 ? 'danger' : 'gray')
                    ->formatStateUsing(fn (int $state): string => $state === 0 ? 'Belum ada soal' : (string) $state)
                    ->sortable(),
                // Sisa stok = total soal topik ini dikurangi soal yang sudah
                // "dipakai" untuk Part yang sudah ada (lihat TopicPartGenerator).
                // Dihitung dari 2 kolom hasil withCount() di atas -- TANPA
                // query tambahan per baris.
                TextColumn::make('sisa_stok')
                    ->label('Sisa Stok')
                    ->state(fn (Topic $record) => $record->questions_count - $record->used_questions_count)
                    ->badge()
                    ->color(function (Topic $record) {
                        $remaining = $record->questions_count - $record->used_questions_count;

                        return match (true) {
                            $remaining < 10 => 'danger',
                            $remaining < 20 => 'warning',
                            default => 'success',
                        };
                    }),
                TextColumn::make('parts_count')
                    ->label('Part Dibuat')
                    ->badge()
                    ->color('gray'),
            ])
            ->defaultSort('name')
            // 25 per halaman -- dengan Tab per Program (lihat ListTopics),
            // jumlah topik per tab biasanya tetap kecil meski total topik
            // lintas semua Program sudah banyak.
            ->defaultPaginationPageOption(25)
            ->paginated([25, 50, 100])
            ->recordUrl(fn (Topic $record) => static::getUrl('edit', ['record' => $record]))
            ->filtersLayout(\Filament\Tables\Enums\FiltersLayout::Dropdown)
            ->deferFilters(false)
            ->filters([
                // Tab bar dihapus -- Program sekarang jadi filter dropdown
                // biasa (pola Shopee: kategori yang bisa terus tumbuh
                // ditaruh di filter, bukan tab).
                SelectFilter::make('program_id')
                    ->label('Program')
                    ->relationship('taxonomy.program', 'name'),
                // Nama Program tetap disertakan di label opsi Kategori,
                // berguna kalau admin belum pilih filter Program di atas.
                SelectFilter::make('taxonomy_id')
                    ->label('Kategori')
                    ->options(fn () => \App\Models\Taxonomy::categories()
                        ->with('program')
                        ->get()
                        ->mapWithKeys(fn ($taxonomy) => [
                            $taxonomy->id => $taxonomy->name . ' (' . ($taxonomy->program->name ?? '-') . ')',
                        ]))
                    ->searchable(),
                Filter::make('belum_ada_soal')
                    ->label('Belum Ada Soal')
                    ->query(fn ($query) => $query->whereDoesntHave('questions'))
                    ->toggle(),
                // Stok menipis sekarang dihitung dari kolom withCount yang
                // sudah dimuat tabel -- HAVING di level SQL, bukan filter
                // manual PHP per baris seperti sebelumnya.
                Filter::make('stok_menipis')
                    ->label('Stok Menipis (< 10)')
                    ->query(fn ($query) => $query->having(
                        \Illuminate\Support\Facades\DB::raw('questions_count - used_questions_count'),
                        '<',
                        10
                    ))
                    ->toggle(),
            ])
            ->emptyStateHeading('Tidak ada Topik yang cocok')
            ->emptyStateDescription('Coba ubah atau hapus filter yang aktif.')
            ->emptyStateIcon('heroicon-o-tag')
            ->emptyStateActions([
                \Filament\Actions\Action::make('resetFilters')
                    ->label('Hapus Semua Filter')
                    ->color('gray')
                    ->action(fn ($livewire) => $livewire->resetTableFiltersForm()),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    // Generate Part massal untuk beberapa Topic sekaligus.
                    // Desain: skip-and-continue (topik dengan stok < jumlah
                    // soal diminta tetap di-skip, bukan gagalin semua batch),
                    // dan modal form ini sendiri jadi langkah konfirmasi
                    // (klik "Generate" = submit, gak ada modal terpisah lagi).
                    BulkAction::make("generatePartMassal")
                        ->label("Generate Part Massal")
                        ->icon("heroicon-o-bolt")
                        ->schema([
                            TextInput::make("jumlah_soal")
                                ->label("Jumlah Soal per Part")
                                ->numeric()
                                ->default(10)
                                ->minValue(10)
                                ->required()
                                ->helperText("Minimal 10 soal per Part."),
                            TextInput::make("durasi_menit")
                                ->label("Durasi (menit)")
                                ->numeric()
                                ->minValue(1)
                                ->helperText("Kosongkan untuk otomatis (1 menit per soal, minimal 5 menit)."),
                        ])
                        ->modalHeading("Generate Part Massal")
                        ->modalDescription(fn ($records) => "Akan generate Part berikutnya untuk {$records->count()} topik terpilih. Topik dengan stok soal kurang dari jumlah yang diminta akan dilewati.")
                        ->modalSubmitActionLabel("Generate")
                        ->action(function ($records, array $data, $livewire) {
                            $questionCount = (int) $data["jumlah_soal"];
                            $durationMinutes = filled($data["durasi_menit"] ?? null) ? (int) $data["durasi_menit"] : null;

                            $success = [];
                            $skipped = [];

                            foreach ($records as $topic) {
                                $category = $topic->taxonomy->name ?? "-";

                                try {
                                    $exam = app(TopicPartGenerator::class)
                                        ->generateNextPart($topic, $questionCount, $durationMinutes);

                                    $success[] = [
                                        "topic" => $topic->name,
                                        "category" => $category,
                                        "part" => $exam->part_number,
                                    ];
                                } catch (\RuntimeException $e) {
                                    $skipped[] = [
                                        "topic" => $topic->name,
                                        "category" => $category,
                                        "reason" => $e->getMessage(),
                                    ];
                                }
                            }

                            $successJson = json_encode($success);
                            $skippedJson = json_encode($skipped);

                            $livewire->js(<<<JS
                            setTimeout(() => {
                                \$wire.mountAction('generatePartMassalResult', {
                                    success: {$successJson},
                                    skipped: {$skippedJson},
                                });
                            }, 300);
                            JS);
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTopics::route('/'),
            'create' => Pages\CreateTopic::route('/create'),
            'edit' => Pages\EditTopic::route('/{record}/edit'),
        ];
    }
}

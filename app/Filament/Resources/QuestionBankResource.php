<?php

namespace App\Filament\Resources;

use App\Filament\Resources\QuestionBankResource\Pages;
use App\Filament\Resources\QuestionBankResource\RelationManagers\QuestionsRelationManager;
use App\Models\Program;
use App\Models\QuestionBank;
use App\Models\Taxonomy;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class QuestionBankResource extends Resource
{
    protected static ?string $model = QuestionBank::class;

    protected static ?string $modelLabel = 'Bank Soal';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static string|\UnitEnum|null $navigationGroup = 'Bank Soal & Ujian';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('program_id')
                ->label('Program')
                ->relationship('program', 'name')
                ->required()
                ->searchable()
                ->preload()
                ->live()
                ->afterStateUpdated(function (Get $get, $set) {
                    $set('taxonomy_id', null);
                })
                ->helperText('Menentukan apakah Bank Soal ini pakai Kategori (CPNS/BUMN) atau Mapel (Sekolah/Masuk Kuliah), sesuai pola Program ini.'),
            Select::make('taxonomy_id')
                ->label(fn (Get $get) => Program::find($get('program_id'))?->usesSubjectMode() ? 'Mapel' : 'Kategori')
                ->options(function (Get $get) {
                    $program = Program::find($get('program_id'));
                    if (! $program) {
                        return [];
                    }
                    return $program->usesSubjectMode()
                        ? Taxonomy::subjects()->pluck('name', 'id')
                        : Taxonomy::categories()->where('program_id', $program->id)->pluck('name', 'id');
                })
                ->searchable()
                ->preload()
                ->live()
                ->required(fn (Get $get) => filled($get('program_id')))
                ->visible(fn (Get $get) => filled($get('program_id')))
                ->helperText(fn (Get $get) => Program::find($get('program_id'))?->usesSubjectMode()
                    ? 'Wajib diisi untuk Program bermode Mapel (mis. SNBT). Bank Soal ini HANYA akan berisi soal mapel ini.'
                    : 'Bank Soal ini HANYA akan berisi soal kategori ini (mis. TWK). Untuk kategori lain, buat Bank Soal terpisah.'),
            TextInput::make('title')->required()->maxLength(255),
            Select::make('scoring_type')
                ->label('Tipe Penilaian')
                ->options([
                    'single_correct' => 'Single Correct (benar/salah, mis. TWK/TIU)',
                    'weighted_options' => 'Weighted Options (bobot per opsi, mis. TKP)',
                ])
                ->live()
                ->visible(fn (Get $get) => filled($get('program_id'))),
            TextInput::make('point_correct')
                ->label('Poin Jika Benar')
                ->numeric()
                ->minValue(0)
                ->visible(fn (Get $get) => $get('scoring_type') === 'single_correct')
                ->required(fn (Get $get) => $get('scoring_type') === 'single_correct'),
            TextInput::make('point_wrong')
                ->label('Poin Jika Salah')
                ->numeric()
                ->default(0)
                ->visible(fn (Get $get) => $get('scoring_type') === 'single_correct')
                ->required(fn (Get $get) => $get('scoring_type') === 'single_correct'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable()->sortable(),
                TextColumn::make('program.name')
                    ->label('Program')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('taxonomy.name')
                    ->label('Mapel / Kategori')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('scoring_type')->badge(),
                // Badge warna: merah = bank kosong (belum diisi soal sama
                // sekali) -- sinyal cepat buat admin tanpa harus buka satu-
                // satu. Sama pola seperti kolom "Jumlah Soal" di TopicResource.
                TextColumn::make('questions_count')
                    ->counts('questions')
                    ->label('Jumlah Soal')
                    ->badge()
                    ->color(fn (int $state): string => $state === 0 ? 'danger' : 'gray')
                    ->formatStateUsing(fn (int $state): string => $state === 0 ? 'Kosong' : (string) $state)
                    ->sortable(),
            ])
            ->defaultSort('title')
            // 25 per halaman -- dengan Tab per Program (lihat ListQuestionBanks),
            // jumlah bank soal per tab tetap kecil meski total lintas semua
            // Program sudah banyak.
            ->defaultPaginationPageOption(25)
            ->paginated([25, 50, 100])
            ->recordUrl(fn (QuestionBank $record) => static::getUrl('edit', ['record' => $record]))
            ->filtersLayout(\Filament\Tables\Enums\FiltersLayout::Dropdown)
            ->deferFilters(false)
            ->filters([
                // Tab bar dihapus -- Program sekarang jadi filter dropdown
                // biasa (pola Shopee: kategori yang bisa terus tumbuh
                // ditaruh di filter, bukan tab).
                SelectFilter::make('program_id')
                    ->label('Program')
                    ->relationship('program', 'name'),
                SelectFilter::make('taxonomy_id')
                    ->label('Mapel / Kategori')
                    ->options(fn () => Taxonomy::query()
                        ->with('program')
                        ->get()
                        ->mapWithKeys(fn ($taxonomy) => [
                            $taxonomy->id => $taxonomy->name . ' (' . ($taxonomy->program->name ?? '-') . ')',
                        ]))
                    ->searchable(),
                SelectFilter::make('scoring_type')
                    ->label('Tipe Penilaian')
                    ->options([
                        'single_correct' => 'Single Correct',
                        'weighted_options' => 'Weighted Options',
                    ]),
                // Quick filter paling sering dicari admin: bank yang masih
                // kosong (butuh segera diisi soal). Sama pola seperti
                // Filter::make('belum_ada_soal') di TopicResource.
                Filter::make('bank_kosong')
                    ->label('Bank Kosong (Belum Ada Soal)')
                    ->query(fn ($query) => $query->whereDoesntHave('questions'))
                    ->toggle(),
            ])
            ->emptyStateHeading('Tidak ada Bank Soal yang cocok')
            ->emptyStateDescription('Coba ubah atau hapus filter yang aktif.')
            ->emptyStateIcon('heroicon-o-archive-box-x-mark')
            ->emptyStateActions([
                \Filament\Actions\Action::make('resetFilters')
                    ->label('Hapus Semua Filter')
                    ->color('gray')
                    ->action(fn ($livewire) => $livewire->resetTableFiltersForm()),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getRelations(): array
    {
        return [
            QuestionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListQuestionBanks::route('/'),
            'create' => Pages\CreateQuestionBank::route('/create'),
            'edit' => Pages\EditQuestionBank::route('/{record}/edit'),
        ];
    }
}

<?php

namespace App\Filament\Resources\QuestionBankResource\RelationManagers;

use App\Models\Topic;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use App\Filament\Imports\QuestionImporter;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Halaman kerja isi soal per Bank Soal. Kategori & tipe penilaian sudah
 * ditentukan sekali di level Bank Soal itu sendiri (lihat QuestionBankResource),
 * jadi form soal di sini tidak punya field kategori lagi -- semua soal yang
 * dibuat di sini otomatis ikut kategori bank-nya.
 *
 * Topik (topic_id) WAJIB bisa dipilih di sini -- ini yang dipakai
 * TopicPartGenerator buat narik soal per topik saat generate Part Latihan
 * (fitur Latihan Soal per Topik/Part). Soal yang tidak ditag Topik TIDAK
 * akan pernah bisa dipakai buat generate Part, jadi field ini penting
 * kelihatan jelas ke admin, bukan cuma bisa diisi lewat import CSV.
 */
class QuestionsRelationManager extends RelationManager
{
    protected static string $relationship = 'questions';

    protected static ?string $title = 'Soal';

    public function form(Schema $schema): Schema
    {
        $bank = $this->getOwnerRecord();
        $isWeighted = $bank->scoring_type === 'weighted_options';
        $taxonomyId = $bank->taxonomy_id;

        return $schema->components([
            Select::make('topic_id')
                ->label('Topik')
                ->options(fn () => Topic::where('taxonomy_id', $taxonomyId)->pluck('name', 'id'))
                ->searchable()
                ->preload()
                ->native(false)
                ->createOptionForm([
                    TextInput::make('code')
                        ->label('Kode Topik')
                        ->required()
                        ->maxLength(255)
                        ->helperText('Kode singkat unik, mis. "figural" atau "SISKUM-01".'),
                    TextInput::make('name')
                        ->label('Nama Topik')
                        ->required()
                        ->maxLength(255),
                ])
                ->createOptionUsing(function (array $data) use ($taxonomyId) {
                    return Topic::create([
                        'taxonomy_id' => $taxonomyId,
                        'code' => $data['code'],
                        'name' => $data['name'],
                    ])->id;
                })
                ->helperText('Wajib diisi supaya soal ini bisa ikut dipakai di fitur "Latihan Soal per Topik/Part". Soal tanpa Topik hanya bisa dipakai untuk Exam biasa (Try Out).')
                ->columnSpanFull(),

            Textarea::make('question_text')
                ->label('Pertanyaan')
                ->required()
                ->rows(4)
                ->columnSpanFull(),

            Select::make('media_type')
                ->label('Jenis Media')
                ->options([
                    'none' => 'Tanpa media',
                    'image' => 'Gambar',
                    'audio' => 'Audio',
                ])
                ->default('none')
                ->live()
                ->required(),

            FileUpload::make('media_url')
                ->label('File Media')
                ->image()
                ->directory('question-media')
                ->maxSize(2048)
                ->visible(fn (Get $get) => $get('media_type') === 'image')
                ->columnSpanFull(),

            TextInput::make('media_url')
                ->label('URL Audio')
                ->helperText('Path atau URL file audio, misal: /storage/questions/audio-1.mp3')
                ->visible(fn (Get $get) => $get('media_type') === 'audio')
                ->columnSpanFull(),

            Select::make('type')
                ->label('Tipe Soal')
                ->options([
                    'pg' => 'Pilihan Ganda',
                    'essay' => 'Essay',
                ])
                ->default('pg')
                ->live()
                ->required(),

            Select::make('difficulty')
                ->label('Tingkat Kesulitan')
                ->options([
                    'mudah' => 'Mudah',
                    'sedang' => 'Sedang',
                    'sulit' => 'Sulit',
                ]),

            Textarea::make('explanation')
                ->label('Pembahasan')
                ->helperText('Penjelasan kunci jawaban yang akan ditampilkan ke siswa di halaman pembahasan soal.')
                ->rows(4)
                ->columnSpanFull(),

            Repeater::make('options')
                ->label('Pilihan Jawaban')
                ->relationship()
                ->schema([
                    TextInput::make('option_text')
                        ->label('Teks Opsi')
                        ->required()
                        ->columnSpan(2),
                    FileUpload::make('image_url')
                        ->label('Gambar Opsi (opsional)')
                        ->image()
                        ->directory('question-option-images')
                        ->maxSize(2048)
                        ->columnSpan(2),
                    Toggle::make('is_correct')
                        ->label('Benar?')
                        ->visible(fn () => ! $isWeighted),
                    TextInput::make('points')
                        ->label('Poin')
                        ->numeric()
                        ->default(0)
                        ->visible(fn () => $isWeighted)
                        ->helperText('Bank ini bertipe Weighted Options -- tiap opsi punya poin sendiri (mis. 1-5), tidak ada opsi "salah".'),
                ])
                ->columns(4)
                ->defaultItems(4)
                ->minItems(2)
                ->addActionLabel('+ Tambah Opsi')
                ->visible(fn (Get $get) => $get('type') === 'pg')
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        $bank = $this->getOwnerRecord();

        return $table
            ->recordTitleAttribute('question_text')
            ->description(function () use ($bank) {
                $totalCount = $bank->questions()->count();
                $taggedCount = $bank->questions()->whereNotNull('topic_id')->count();
                $untaggedCount = $totalCount - $taggedCount;

                return "Total soal: {$totalCount} · Sudah ditag Topik: {$taggedCount} · Belum ditag: {$untaggedCount}" .
                    ($untaggedCount > 0 ? ' — ⚠️ soal yang belum ditag Topik tidak bisa dipakai di Latihan Soal per Part.' : '');
            })
            ->columns([
                TextColumn::make('question_text')->label('Pertanyaan')->limit(70)->wrap(),
                TextColumn::make('topic.name')
                    ->label('Topik')
                    ->badge()
                    ->color(fn ($state) => filled($state) ? 'success' : 'danger')
                    ->placeholder('Belum ditag')
                    ->default('Belum ditag'),
                TextColumn::make('type')->badge(),
                TextColumn::make('difficulty')->badge(),
                TextColumn::make('options_count')->counts('options')->label('Jml Opsi'),
            ])
            ->filters([
                SelectFilter::make('topic_id')
                    ->label('Topik')
                    ->options(fn () => Topic::where('taxonomy_id', $bank->taxonomy_id)->pluck('name', 'id')),
            ])
            ->headerActions([
                CreateAction::make(),
                ImportAction::make()
                    ->label('Import Soal')
                    ->importer(QuestionImporter::class)
                    ->options(fn () => ['bank_id' => $this->getOwnerRecord()->getKey()])
                    ->maxRows(1000)
                    ->chunkSize(50),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}

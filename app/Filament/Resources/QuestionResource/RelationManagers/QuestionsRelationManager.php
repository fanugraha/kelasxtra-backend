<?php

namespace App\Filament\Resources\QuestionResource\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use App\Filament\Imports\QuestionImporter;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Halaman kerja isi soal per Bank Soal (Paket). Tab dipecah per kategori
 * (TWK/TIU/TKP/dll) sesuai question_bank_sections, dengan progress count
 * vs target di label tab.
 */
class QuestionsRelationManager extends RelationManager
{
    protected static string $relationship = 'questions';

    protected static ?string $title = 'Soal';

    public function form(Schema $schema): Schema
    {
        $bank = $this->getOwnerRecord();

        return $schema->components([
            Select::make('category_id')
                ->label('Kategori')
                ->options($bank->program->categories()->pluck('name', 'id'))
                ->required()
                ->searchable(),

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

            TextInput::make('media_url')
                ->label('URL Media')
                ->helperText('Path atau URL file gambar/audio, misal: /storage/questions/soal-1.png')
                ->visible(fn (Get $get) => $get('media_type') !== 'none')
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
                    Toggle::make('is_correct')
                        ->label('Benar?'),
                    TextInput::make('points')
                        ->label('Poin')
                        ->numeric()
                        ->default(0)
                        ->helperText('Untuk TKP: tiap opsi bisa punya poin beda'),
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
        return $table
            ->recordTitleAttribute('question_text')
            ->columns([
                TextColumn::make('question_text')->label('Pertanyaan')->limit(70)->wrap(),
                TextColumn::make('type')->badge(),
                TextColumn::make('difficulty')->badge(),
                TextColumn::make('options_count')->counts('options')->label('Jml Opsi'),
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

    public function getTabs(): array
    {
        $bank = $this->getOwnerRecord();
        $tabs = [];

        foreach ($bank->sections as $section) {
            $count = $bank->questions()->where('category_id', $section->category_id)->count();
            $label = ($section->category->name ?? 'Tanpa Kategori') . " ({$count}/{$section->target_count})";

            $tabs[(string) $section->category_id] = Tab::make($label)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('category_id', $section->category_id));
        }

        return $tabs;
    }
}
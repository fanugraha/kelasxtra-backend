<?php

namespace App\Filament\Resources\QuestionBankResource\Pages;

use App\Filament\Resources\QuestionBankResource;
use App\Models\Category;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class CreateQuestionBank extends CreateRecord
{
    protected static string $resource = QuestionBankResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Wizard::make([
                Step::make('Program')
                    ->schema([
                        Select::make('program_id')
                            ->label('Program')
                            ->relationship('program', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                $categories = Category::where('program_id', $get('program_id'))->get();

                                $set('sections', $categories->map(fn (Category $category) => [
                                    'category_id' => $category->id,
                                    'target_count' => 0,
                                ])->toArray());
                            }),
                    ]),

                Step::make('Nama Paket')
                    ->schema([
                        TextInput::make('title')
                            ->label('Nama Paket')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Misal: TIU - Paket 2'),
                        Select::make('subject_id')
                            ->label('Mapel (opsional)')
                            ->relationship('subject', 'name')
                            ->searchable()
                            ->preload(),
                    ]),

                Step::make('Target Soal per Kategori')
                    ->schema([
                        Repeater::make('sections')
                            ->label('')
                            ->schema([
                                Select::make('category_id')
                                    ->label('Kategori')
                                    ->options(
                                        fn (Get $get) => Category::where(
                                            'program_id',
                                            $get('../../program_id')
                                        )->pluck('name', 'id')
                                    )
                                    ->required()
                                    ->searchable(),
                                TextInput::make('target_count')
                                    ->label('Target Jumlah Soal')
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(0)
                                    ->required(),
                            ])
                            ->columns(2)
                            ->addActionLabel('+ Tambah Kategori')
                            ->helperText('Otomatis terisi dari kategori Program yang dipilih di Step 1. Ubah target soal, atau tambah/hapus baris manual.'),
                    ]),
            ])->columnSpanFull(),
        ]);
    }

    protected function handleRecordCreation(array $data): Model
    {
        $sections = $data['sections'] ?? [];
        unset($data['sections']);

        $questionBank = static::getModel()::create($data);

        foreach ($sections as $section) {
            if (empty($section['category_id'])) {
                continue;
            }

            $questionBank->sections()->create([
                'category_id' => $section['category_id'],
                'target_count' => $section['target_count'] ?? 0,
            ]);
        }

        return $questionBank;
    }
}
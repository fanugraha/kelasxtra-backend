<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProgramResource\Pages;
use App\Models\Program;
use App\Filament\Resources\ProgramResource\RelationManagers\CategoriesRelationManager;
use App\Filament\Resources\ProgramResource\RelationManagers\SubjectsRelationManager;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ProgramResource extends Resource
{
    protected static ?string $model = Program::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static string|\UnitEnum|null $navigationGroup = 'Konten Program';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($state, callable $set) => $set('slug', Str::slug($state))),
            TextInput::make('slug')
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true),
            Textarea::make('description')
                ->rows(3)
                ->columnSpanFull(),
            TextInput::make('icon')
                ->maxLength(255)
                ->helperText('Nama icon (opsional), misalnya nama file atau class icon.'),
            // BARU: menandai pola pengelompokan soal Program ini, supaya form
            // Bank Soal bisa otomatis menampilkan field Kategori ATAU Mapel
            // saja (bukan dua-duanya sekaligus) tergantung pilihan di sini.
            Select::make('question_grouping_mode')
                ->label('Pola Pengelompokan Soal')
                ->options([
                    'category' => 'Kategori (CPNS/BUMN/Kedinasan -- TWK/TIU/TKP, banyak bagian sekaligus per exam)',
                    'subject' => 'Mapel (Sekolah/Masuk Kuliah -- Matematika/Fisika, latihan satu-satu)',
                ])
                ->required()
                ->default('category')
                ->helperText('Menentukan apakah Bank Soal di Program ini dikelompokkan pakai Kategori atau Mapel.'),
            Toggle::make('is_active')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('slug')->searchable(),
                TextColumn::make('packages_count')->counts('packages')->label('Jumlah Paket'),
                TextColumn::make('question_grouping_mode')
                    ->label('Pola Soal')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'subject' ? 'Mapel' : 'Kategori'),
                IconColumn::make('is_active')->boolean(),
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

    public static function getRelations(): array
    {
        return [
            CategoriesRelationManager::class,
            SubjectsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPrograms::route('/'),
            'create' => Pages\CreateProgram::route('/create'),
            'edit' => Pages\EditProgram::route('/{record}/edit'),
        ];
    }
}

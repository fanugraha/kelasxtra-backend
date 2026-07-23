<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TopicResource\Pages;
use App\Models\Topic;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
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
            ->columns([
                TextColumn::make('taxonomy.name')->label('Kategori')->searchable()->sortable(),
                TextColumn::make('code')->searchable()->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('questions_count')->counts('questions')->label('Jumlah Soal'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
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

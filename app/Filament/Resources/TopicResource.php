<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TopicResource\Pages;
use App\Models\Program;
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
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
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
                TextColumn::make('taxonomy.program.name')
                    ->label('Program')
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('taxonomy.name')->label('Kategori')->searchable()->sortable(),
                TextColumn::make('code')->searchable()->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('questions_count')
                    ->counts('questions')
                    ->label('Jumlah Soal')
                    ->badge()
                    ->color(fn (int $state): string => $state === 0 ? 'danger' : 'gray')
                    ->formatStateUsing(fn (int $state): string => $state === 0 ? 'Belum ada soal' : (string) $state)
                    ->sortable(),
            ])
            ->defaultGroup(
                Group::make('taxonomy.name')
                    ->label('Kategori')
                    ->collapsible()
            )
            ->groups([
                Group::make('taxonomy.name')->label('Kategori'),
                Group::make('taxonomy.program.name')->label('Program'),
            ])
            ->defaultSort('name')
            ->filters([
                // BARU: filter Program terpisah -- begitu ada Program baru
                // (mis. BUMN), admin bisa nyaring dulu ke 1 program sebelum
                // pilih Kategori, jadi dropdown Kategori di bawah nggak
                // nyampur nama yang mirip dari program berbeda.
                SelectFilter::make('program_id')
                    ->label('Program')
                    ->options(fn () => Program::pluck('name', 'id'))
                    ->query(function ($query, array $data) {
                        return $query->when(
                            $data['value'] ?? null,
                            fn ($q, $value) => $q->whereHas(
                                'taxonomy',
                                fn ($q2) => $q2->where('program_id', $value)
                            )
                        );
                    }),
                // Label kategori dikasih nama Program-nya juga (disambiguasi)
                // supaya kalau ada 2 program dengan nama kategori sama,
                // admin tetap bisa bedain di dropdown.
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

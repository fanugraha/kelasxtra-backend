<?php

namespace App\Filament\Resources\ExamResource\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BatchesRelationManager extends RelationManager
{
    protected static string $relationship = 'batches';

    protected static ?string $title = 'Batch Try Out';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->helperText('Contoh: "Try Out Nasional Batch 3"'),
            DateTimePicker::make('start_at')->required(),
            DateTimePicker::make('end_at')->required()->after('start_at'),
            Toggle::make('is_national')->label('Ranking Nasional'),
            Select::make('status')
                ->options([
                    'scheduled' => 'Scheduled',
                    'ongoing' => 'Ongoing',
                    'finished' => 'Finished',
                    'ranked' => 'Ranked',
                ])
                ->default('scheduled')
                ->required()
                ->helperText('Status "ranked" otomatis diisi sistem setelah leaderboard di-generate. Jangan diubah manual kalau bukan untuk keperluan testing.'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('start_at')->dateTime('d M Y H:i'),
                TextColumn::make('end_at')->dateTime('d M Y H:i'),
                IconColumn::make('is_national')->boolean(),
                TextColumn::make('status')->badge(),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}

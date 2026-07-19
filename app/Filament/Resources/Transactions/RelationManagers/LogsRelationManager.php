<?php

namespace App\Filament\Resources\Transactions\RelationManagers;

use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class LogsRelationManager extends RelationManager
{
    protected static string $relationship = 'logs';

    protected static ?string $title = 'Riwayat Log Midtrans';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d M Y H:i:s')
                    ->sortable(),
                TextColumn::make('status_summary')
                    ->label('Ringkasan Status')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'settlement', 'capture' => 'success',
                        'pending' => 'warning',
                        'deny', 'cancel', 'expire', 'failure' => 'danger',
                        default => 'gray',
                    })
                    ->getStateUsing(fn ($record) => data_get($record->raw_payload, 'transaction_status')
                        ?? data_get($record->raw_payload, 'status')
                        ?? '—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([])
            ->recordActions([
                Action::make('viewPayload')
                    ->label('Lihat Payload')
                    ->icon('heroicon-m-code-bracket')
                    ->modalHeading('Raw Payload')
                    ->modalContent(fn ($record) => new HtmlString(
                        '<pre style="white-space: pre-wrap; font-size: 12px;">'
                        .e(json_encode($record->raw_payload, JSON_PRETTY_PRINT))
                        .'</pre>'
                    ))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup'),
            ])
            ->toolbarActions([]);
    }
}

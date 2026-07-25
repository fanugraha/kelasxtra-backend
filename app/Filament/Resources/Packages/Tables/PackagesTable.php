<?php

namespace App\Filament\Resources\Packages\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PackagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('program.name')
                    ->label('Program')
                    ->placeholder('—')
                    ->searchable(),
                // SEDERHANA: daripada strip kosong yang ambigu, tulis alasan
                // kosongnya langsung. Admin gak perlu nebak "kosong karena
                // belum diisi" atau "kosong karena memang gak relevan".
                TextColumn::make('taxonomy.name')
                    ->label('Mapel')
                    ->getStateUsing(fn ($record) => $record->taxonomy?->name
                        ?? ($record->program?->usesSubjectMode() ? 'Belum dipilih' : 'Tidak berlaku'))
                    ->color(fn ($record) => $record->taxonomy_id ? null : 'gray')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('type')
                    ->badge(),
                // SEDERHANA: toggle "Fokus Topik" + kolom "Topik" digabung
                // jadi 1 kolom saja, karena keduanya memang satu konsep
                // (boolean yang menentukan ada-tidaknya sebuah value).
                // Hasilnya langsung "Semua Topik" (default/reguler) atau
                // nama topiknya -- admin baca 1 kolom, bukan 2.
                TextColumn::make('topik_fokus')
                    ->label('Topik')
                    ->badge()
                    ->getStateUsing(fn ($record) => $record->is_focus_topic
                        ? ($record->focusTaxonomy?->name ?? 'Belum dipilih')
                        : 'Semua Topik')
                    ->color(fn ($record) => $record->is_focus_topic ? 'warning' : 'gray'),
                TextColumn::make('price')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('discount_price')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('duration_days')
                    ->label('Durasi Akses')
                    ->getStateUsing(fn ($record) => filled($record->duration_days)
                        ? $record->duration_days . ' hari'
                        : 'Lifetime')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filtersLayout(\Filament\Tables\Enums\FiltersLayout::Dropdown)
            ->deferFilters(false)
            ->filters([
                // Tab bar dihapus -- Program sekarang jadi filter dropdown
                // biasa (pola Shopee: kategori yang bisa terus tumbuh
                // ditaruh di filter, bukan tab).
                SelectFilter::make('program_id')
                    ->label('Program')
                    ->relationship('program', 'name'),
                SelectFilter::make('is_focus_topic')
                    ->label('Topik')
                    ->options([
                        '1' => 'Fokus 1 Topik',
                        '0' => 'Semua Topik',
                    ]),
                SelectFilter::make('type')
                    ->label('Tipe Paket')
                    ->options(fn () => \App\Models\Package::query()
                        ->distinct()
                        ->pluck('type', 'type')),
                // Sinyal "paket belum siap dijual": mode Fokus Topik aktif
                // tapi topiknya belum dipilih -- ini yang bikin kolom Topik
                // nampilin "Belum dipilih" di tabel. Biasanya paket draft
                // yang lupa dilengkapi, price-nya pun sering masih 0.
                Filter::make('belum_lengkap')
                    ->label('Belum Lengkap (Fokus Topik Belum Dipilih)')
                    ->query(fn ($query) => $query->where('is_focus_topic', true)
                        ->whereNull('focus_taxonomy_id'))
                    ->toggle(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                ReplicateAction::make()
                    ->label('Duplikat')
                    ->excludeAttributes(['created_at', 'updated_at'])
                    ->beforeReplicaSaved(function ($replica) {
                        $replica->name = $replica->name . ' (Copy)';
                    })
                    ->after(function ($record, $replica) {
                        $replica->exams()->sync($record->exams()->pluck('exams.id'));
                    })
                    ->successNotificationTitle('Paket berhasil diduplikat, tinggal ubah exam-nya'),
            ])
            ->emptyStateHeading('Tidak ada Paket yang cocok')
            ->emptyStateDescription('Coba ubah atau hapus filter yang aktif.')
            ->emptyStateIcon('heroicon-o-cube')
            ->emptyStateActions([
                \Filament\Actions\Action::make('resetFilters')
                    ->label('Hapus Semua Filter')
                    ->color('gray')
                    ->action(fn ($livewire) => $livewire->resetTableFiltersForm()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

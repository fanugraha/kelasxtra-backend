<?php

namespace App\Filament\Resources\Transactions\Pages;

use App\Filament\Resources\Transactions\TransactionResource;
use App\Models\Transaction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListTransactions extends ListRecords
{
    protected static string $resource = TransactionResource::class;

    /**
     * Tab per STATUS, bukan per Program -- beda dari Topics/Bank Soals/
     * Exams/Packages. Yang paling sering dicari admin di sini adalah
     * "mana yang masih pending" atau "mana yang gagal", bukan filter
     * Program (itu tetap tersedia lewat kolom Paket -> Program kalau
     * suatu saat dibutuhkan, tapi bukan kebutuhan utama harian).
     *
     * Warna badge dan urutan tab konsisten dengan kolom Status di
     * TransactionsTable: pending=warning, success=success, failed=danger,
     * expired=gray. Tab "Pending" diletakkan di depan (bukan "Semua")
     * karena itu yang paling butuh perhatian admin tiap hari.
     */
    public function getTabs(): array
    {
        $countsByStatus = Transaction::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'pending' => Tab::make('Pending')
                ->badge($countsByStatus['pending'] ?? 0)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn ($query) => $query->where('status', 'pending')),
            'success' => Tab::make('Sukses')
                ->badge($countsByStatus['success'] ?? 0)
                ->badgeColor('success')
                ->modifyQueryUsing(fn ($query) => $query->where('status', 'success')),
            'failed' => Tab::make('Gagal')
                ->badge($countsByStatus['failed'] ?? 0)
                ->badgeColor('danger')
                ->modifyQueryUsing(fn ($query) => $query->where('status', 'failed')),
            'expired' => Tab::make('Kedaluwarsa')
                ->badge($countsByStatus['expired'] ?? 0)
                ->badgeColor('gray')
                ->modifyQueryUsing(fn ($query) => $query->where('status', 'expired')),
            'semua' => Tab::make('Semua')
                ->badge(Transaction::count()),
        ];
    }
}

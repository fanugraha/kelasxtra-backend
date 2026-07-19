<?php

namespace App\Filament\Widgets;

use App\Models\Transaction;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RevenueStatsWidget extends BaseWidget
{
    protected static ?int $sort = -10;

    protected function getStats(): array
    {
        $bulanIni = Transaction::where('status', 'success')
            ->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->selectRaw('SUM(amount - discount_amount) as total')
            ->value('total') ?? 0;

        $keseluruhan = Transaction::where('status', 'success')
            ->selectRaw('SUM(amount - discount_amount) as total')
            ->value('total') ?? 0;

        $jumlahTransaksiBulanIni = Transaction::where('status', 'success')
            ->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->count();

        return [
            Stat::make('Pendapatan Bulan Ini', 'Rp ' . number_format($bulanIni, 0, ',', '.'))
                ->description("{$jumlahTransaksiBulanIni} transaksi sukses bulan " . now()->translatedFormat('F Y'))
                ->color('success'),
            Stat::make('Total Pendapatan (Keseluruhan)', 'Rp ' . number_format($keseluruhan, 0, ',', '.'))
                ->description('Akumulasi semua transaksi sukses')
                ->color('primary'),
        ];
    }
}

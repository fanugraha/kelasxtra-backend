<?php

namespace App\Filament\Widgets;

use App\Models\PracticeLeaderboard;
use App\Models\Promo;
use App\Models\Transaction;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class LeaderboardRewardStatsWidget extends BaseWidget
{
    protected static ?int $sort = 0;

    protected function getStats(): array
    {
        $periode = $this->currentPeriode();

        $totalVoucher = PracticeLeaderboard::where('periode', $periode)
            ->whereNotNull('discount_code')
            ->count();

        $codes = PracticeLeaderboard::where('periode', $periode)
            ->whereNotNull('discount_code')
            ->pluck('discount_code');

        $promos = Promo::whereIn('code', $codes)->get();

        $terpakai = $promos->filter(function ($p) {
            return Transaction::where('promo_id', $p->id)
                ->where('status', 'success')
                ->exists();
        })->count();

        $terpakaiPersen = $totalVoucher > 0
            ? round(($terpakai / $totalVoucher) * 100)
            : 0;

        $totalNominal = $promos->sum('max_discount_amount');

        return [
            Stat::make('Voucher — Periode Ini', "{$totalVoucher} dibuat")
                ->description("{$terpakai} terpakai ({$terpakaiPersen}%) · Periode {$periode}")
                ->color('primary'),
            Stat::make('Estimasi Nominal Voucher', 'Rp ' . number_format($totalNominal, 0, ',', '.'))
                ->description('Total voucher dibuat periode ini')
                ->color('warning'),
        ];
    }

    protected function currentPeriode(): string
    {
        $now = now();

        return $now->format('o') . '-W' . str_pad((string) $now->isoWeek(), 2, '0', STR_PAD_LEFT);
    }
}

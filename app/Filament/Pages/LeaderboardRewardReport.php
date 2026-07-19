<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\LeaderboardRewardStatsWidget;
use App\Models\PracticeLeaderboard;
use App\Models\Promo;
use App\Models\Transaction;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

class LeaderboardRewardReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.leaderboard-reward-report';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Leaderboard Reward Report';

    protected static ?string $title = 'Leaderboard Reward Report';

    public function getSubheading(): ?string
    {
        return 'Kartu = periode berjalan saja. Tabel = histori semua periode.';
    }

    protected function getHeaderWidgets(): array
    {
        return [
            LeaderboardRewardStatsWidget::class,
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                PracticeLeaderboard::query()
                    ->whereNotNull('discount_code')
                    ->selectRaw('MIN(id) as id, periode, COUNT(*) as voucher_dibuat')
                    ->groupBy('periode')
            )
            // Matikan tie-breaker "ORDER BY <table>.id" otomatis dari Filament —
            // itu yang bentrok dengan GROUP BY di sql_mode=only_full_group_by.
            ->defaultKeySort(false)
            ->defaultSort('periode', 'desc')
            ->recordClasses(fn ($record) => $record->periode === $this->currentPeriode()
                ? 'bg-primary-50 dark:bg-primary-500/10'
                : null)
            ->columns([
                TextColumn::make('periode')
                    ->label('Periode')
                    ->weight('semibold'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(fn ($record) => $record->periode === $this->currentPeriode() ? 'Berjalan' : 'Selesai')
                    ->color(fn ($record) => $record->periode === $this->currentPeriode() ? 'success' : 'gray'),
                TextColumn::make('voucher_dibuat')
                    ->label('Voucher Dibuat')
                    ->numeric()
                    ->alignEnd(),
                TextColumn::make('terpakai')
                    ->label('Terpakai')
                    ->alignEnd()
                    ->state(fn ($record) => $this->countUsed($record->periode)),
                TextColumn::make('hangus')
                    ->label('Hangus')
                    ->alignEnd()
                    ->state(fn ($record) => $this->countExpired($record->periode)),
                TextColumn::make('estimasi_total')
                    ->label('Estimasi Total Diskon')
                    ->alignEnd()
                    ->weight('semibold')
                    ->state(fn ($record) => $this->estimasiTotal($record->periode)),
            ])
            ->paginated(false);
    }

    protected function currentPeriode(): string
    {
        $now = now();

        return $now->format('o') . '-W' . str_pad((string) $now->isoWeek(), 2, '0', STR_PAD_LEFT);
    }

    protected function codesFor(string $periode): Collection
    {
        return PracticeLeaderboard::where('periode', $periode)
            ->whereNotNull('discount_code')
            ->pluck('discount_code');
    }

    protected function promosFor(string $periode): Collection
    {
        return Promo::whereIn('code', $this->codesFor($periode))->get();
    }

    protected function countUsed(string $periode): int
    {
        return $this->promosFor($periode)
            ->filter(fn ($promo) => Transaction::where('promo_id', $promo->id)
                ->where('status', 'success')
                ->exists())
            ->count();
    }

    protected function countExpired(string $periode): int
    {
        return $this->promosFor($periode)
            ->filter(function ($promo) {
                $used = Transaction::where('promo_id', $promo->id)
                    ->where('status', 'success')
                    ->exists();

                return ! $used && $promo->valid_until && now()->gt($promo->valid_until);
            })
            ->count();
    }

    protected function estimasiTotal(string $periode): string
    {
        $total = $this->promosFor($periode)->sum('max_discount_amount');

        return 'Rp ' . number_format((float) $total, 0, ',', '.');
    }
}

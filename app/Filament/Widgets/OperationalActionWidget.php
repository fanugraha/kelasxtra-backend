<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Enrollments\EnrollmentResource;
use App\Filament\Resources\Transactions\TransactionResource;
use App\Models\Enrollment;
use App\Models\Transaction;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OperationalActionWidget extends BaseWidget
{
    // Tampil paling atas dashboard — hal yang perlu ditindaklanjuti admin
    // dilihat duluan, sebelum statistik voucher.
    protected static ?int $sort = -25;

    protected function getStats(): array
    {
        $pendingTransactions = Transaction::where('status', 'pending')->count();
        $pendingEnrollments = Enrollment::where('status', 'pending')->count();

        return [
            Stat::make('Transaksi Menunggu Konfirmasi', $pendingTransactions)
                ->description($pendingTransactions > 0
                    ? 'Klik untuk cek & tindak lanjuti'
                    : 'Tidak ada yang menunggu')
                ->color($pendingTransactions > 0 ? 'warning' : 'success')
                ->url(TransactionResource::getUrl('index', [
                    'tableFilters' => ['status' => ['value' => 'pending']],
                ])),

            Stat::make('Enrollment Belum Aktif', $pendingEnrollments)
                ->description($pendingEnrollments > 0
                    ? 'Siswa belum bisa akses kelas — klik untuk aktifkan'
                    : 'Semua enrollment sudah aktif')
                ->color($pendingEnrollments > 0 ? 'warning' : 'success')
                ->url(EnrollmentResource::getUrl('index', [
                    'tableFilters' => ['status' => ['value' => 'pending']],
                ])),
        ];
    }
}

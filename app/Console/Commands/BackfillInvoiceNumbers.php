<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillInvoiceNumbers extends Command
{
    protected $signature = 'transactions:backfill-invoice-numbers {--dry-run : Tampilkan apa yang akan diubah tanpa benar-benar menyimpan}';

    protected $description = 'Isi invoice_number untuk transaksi lama (dibuat sebelum kolom ini ada) sesuai format INV-YYYYMMDD-NNNN, urut berdasarkan created_at';

    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');

        $transactions = Transaction::whereNull('invoice_number')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        if ($transactions->isEmpty()) {
            $this->info('Tidak ada transaksi dengan invoice_number kosong. Tidak ada yang perlu di-backfill.');
            return self::SUCCESS;
        }

        $this->info("Menemukan {$transactions->count()} transaksi tanpa invoice_number.");

        $sequencePerDay = [];
        $updates = [];

        foreach ($transactions as $transaction) {
            $dateKey = $transaction->created_at->format('Ymd');
            $sequencePerDay[$dateKey] = ($sequencePerDay[$dateKey] ?? 0) + 1;

            $invoiceNumber = sprintf(
                'INV-%s-%04d',
                $dateKey,
                $sequencePerDay[$dateKey]
            );

            $updates[] = [
                'id' => $transaction->id,
                'created_at' => $transaction->created_at->toDateTimeString(),
                'old_order_id' => $transaction->midtrans_order_id,
                'new_invoice_number' => $invoiceNumber,
            ];
        }

        $this->table(
            ['ID', 'Dibuat', 'Order ID', 'Invoice Number Baru'],
            array_map(fn ($u) => [$u['id'], $u['created_at'], $u['old_order_id'], $u['new_invoice_number']], $updates)
        );

        if ($isDryRun) {
            $this->warn('Dry-run: tidak ada perubahan yang disimpan. Jalankan tanpa --dry-run untuk menerapkan.');
            return self::SUCCESS;
        }

        if (! $this->confirm('Lanjutkan menyimpan ' . count($updates) . ' invoice_number di atas?', true)) {
            $this->warn('Dibatalkan.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($updates) {
            foreach ($updates as $u) {
                Transaction::where('id', $u['id'])->update([
                    'invoice_number' => $u['new_invoice_number'],
                ]);
            }
        });

        $this->info('Berhasil mengisi invoice_number untuk ' . count($updates) . ' transaksi lama.');

        return self::SUCCESS;
    }
}

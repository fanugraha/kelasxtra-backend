<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Midtrans\Config;
use Midtrans\Transaction as MidtransTransaction;

class ReconcilePendingTransactions extends Command
{
    protected $signature = 'transactions:reconcile';

    protected $description = 'Cek ulang status transaksi pending yang sudah lewat batas waktu ke Midtrans — jaga-jaga webhook gagal/telat terkirim';

    public function handle(): int
    {
        Config::$serverKey = config('services.midtrans.server_key');
        Config::$isProduction = (bool) config('services.midtrans.is_production');

        // Snap VA/QRIS default kedaluwarsa 24 jam. Beri jeda 1 jam ekstra
        // sebelum kita anggap perlu dicek ulang, supaya tidak balapan dengan
        // webhook 'expire' yang barangkali baru mau terkirim wajar.
        $stale = Transaction::where('status', 'pending')
            ->where('created_at', '<=', now()->subHours(25))
            ->get();

        $this->info("Menemukan {$stale->count()} transaksi pending yang sudah lewat batas waktu.");

        foreach ($stale as $transaction) {
            try {
                $status = MidtransTransaction::status($transaction->midtrans_order_id);
                $this->reconcileOne($transaction, (array) json_decode(json_encode($status), true));
            } catch (\Exception $e) {
                Log::warning("Gagal cek status Midtrans untuk order {$transaction->midtrans_order_id}: " . $e->getMessage());
            }
        }

        return self::SUCCESS;
    }

    protected function reconcileOne(Transaction $transaction, array $payload): void
    {
        $transactionStatus = $payload['transaction_status'] ?? null;

        $newStatus = match (true) {
            in_array($transactionStatus, ['capture', 'settlement']) => 'success',
            in_array($transactionStatus, ['deny', 'cancel']) => 'failed',
            $transactionStatus === 'expire' => 'expired',
            default => null,
        };

        if ($newStatus === null) {
            return;
        }

        $transaction->update([
            'status' => $newStatus,
            'payment_method' => $payload['payment_type'] ?? $transaction->payment_method,
            'paid_at' => $newStatus === 'success' ? now() : $transaction->paid_at,
        ]);

        $this->info("Order {$transaction->midtrans_order_id} -> {$newStatus}");
    }
}
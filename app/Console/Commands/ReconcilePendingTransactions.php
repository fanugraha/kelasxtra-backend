<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Services\TransactionAccessService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Midtrans\Config;
use Midtrans\Transaction as MidtransTransaction;

class ReconcilePendingTransactions extends Command
{
    protected $signature = 'transactions:reconcile';

    protected $description = 'Cek ulang status transaksi pending yang sudah lewat batas waktu ke Midtrans — jaga-jaga webhook gagal/telat terkirim';

    public function __construct(protected TransactionAccessService $transactionAccess)
    {
        parent::__construct();
    }

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
            in_array($transactionStatus, ['refund', 'partial_refund']) => 'refunded',
            default => null,
        };

        if ($newStatus === null) {
            return;
        }

        $transaction->logs()->create([
            'raw_payload' => $payload,
            'source' => 'reconcile',
        ]);

        $transaction->update([
            'status' => $newStatus,
            'payment_method' => $payload['payment_type'] ?? $transaction->payment_method,
            'paid_at' => $newStatus === 'success' ? now() : $transaction->paid_at,
        ]);

        // Transaction::booted() cuma mengubah status Enrollment/Subscription
        // yang SUDAH ADA -- untuk pembelian pertama kali (baris baru), akses
        // wajib diberikan eksplisit lewat TransactionAccessService di sini,
        // sama seperti yang dilakukan MidtransCallbackController untuk
        // webhook real-time. Tanpa ini, transaksi yang baru ke-granted lewat
        // reconcile (bukan webhook) akan tercatat 'success' tapi siswanya
        // tidak pernah benar-benar dapat akses ke paket/langganannya.
        if ($newStatus === 'success') {
            $this->transactionAccess->grantAccessOnSuccess($transaction->fresh());
        }

        $this->info("Order {$transaction->midtrans_order_id} -> {$newStatus}");
    }
}
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\Enrollment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MidtransCallbackController extends Controller
{
    /**
     * Handle webhook callback dari Midtrans secara idempotent.
     */
    public function handleCallback(Request $request)
    {
        $payload = $request->all();

        $orderId = $payload['order_id'] ?? null;
        $statusCode = $payload['status_code'] ?? null;
        $grossAmount = $payload['gross_amount'] ?? null;
        $transactionStatus = $payload['transaction_status'] ?? null;
        $fraudStatus = $payload['fraud_status'] ?? null;
        $type = $payload['payment_type'] ?? null;

        // 1. Validasi SHA512 Signature Key untuk memastikan request asli dari Midtrans
        $serverKey = config('services.midtrans.server_key');
        $signature = hash("sha512", $orderId . $statusCode . $grossAmount . $serverKey);

        if ($signature !== ($payload['signature_key'] ?? '')) {
            Log::warning("Midtrans Callback: Invalid Signature untuk Order ID: {$orderId}");
            return response()->json(['message' => 'Invalid signature'], 403);
        }

        // 2. Gunakan DB Transaction + Row Locking (lockForUpdate) untuk mencegah race condition
        return DB::transaction(function () use ($orderId, $transactionStatus, $fraudStatus, $type, $payload) {
            $transaction = Transaction::where('midtrans_order_id', $orderId)
                ->lockForUpdate()
                ->first();

            if (!$transaction) {
                return response()->json(['message' => 'Transaction tidak ditemukan'], 404);
            }

            // Catat raw payload ke transaction_logs untuk audit trail
            $transaction->logs()->create([
                'raw_payload' => $payload
            ]);

            // IDEMPOTENCY GUARD: kalau status di DB sudah final (success/failed/expired),
            // webhook susulan/duplikat tidak boleh mengubah apa pun lagi.
            if (in_array($transaction->status, ['success', 'failed', 'expired'], true)) {
                return response()->json(['message' => 'Webhook sudah diproses sebelumnya (Idempotent)']);
            }

            // 3. Mapping status Midtrans -> status lokal.
            // Khusus 'capture': status akhirnya tergantung fraud_status dari Midtrans FDS
            // (Fraud Detection System) -- bukan otomatis sukses.
            //   - accept    -> sukses
            //   - challenge -> perlu review manual, JANGAN diaktifkan dulu (skip update)
            //   - lainnya   -> ditolak sistem fraud, dianggap gagal
            if ($transactionStatus === 'capture') {
                if ($fraudStatus === 'challenge') {
                    Log::info("Midtrans Callback: Order {$orderId} status capture+challenge, menunggu review fraud manual.");
                    return response()->json(['message' => 'Transaksi menunggu review fraud, belum diproses']);
                }
                $newStatus = $fraudStatus === 'accept' ? 'success' : 'failed';
            } elseif ($transactionStatus === 'settlement') {
                $newStatus = 'success';
            } elseif (in_array($transactionStatus, ['deny', 'cancel'])) {
                $newStatus = 'failed';
            } elseif ($transactionStatus === 'expire') {
                $newStatus = 'expired';
            } else {
                $newStatus = 'pending';
            }

            // 4. Update status transaksi
            $transaction->update([
                'status' => $newStatus,
                'payment_method' => $type,
                'paid_at' => $newStatus === 'success' ? now() : null
            ]);

            // 5. Jika pembayaran sukses, buat atau aktifkan Enrollment siswa
            if ($newStatus === 'success') {
                Enrollment::updateOrCreate(
                    [
                        'user_id' => $transaction->user_id,
                        'package_id' => $transaction->package_id,
                    ],
                    [
                        'transaction_id' => $transaction->id,
                        'status' => 'active',
                        'start_date' => now(),
                        'end_date' => $transaction->package->duration_days
    ? now()->addDays($transaction->package->duration_days)
    : null,
                    ]
                );

                Log::info("Midtrans Callback: Enrollment berhasil diaktifkan untuk Order ID: {$orderId}");
            }

            return response()->json(['message' => 'Status transaksi berhasil diperbarui']);
        });
    }
}

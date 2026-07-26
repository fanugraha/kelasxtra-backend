<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\TransactionAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MidtransCallbackController extends Controller
{
    public function __construct(protected TransactionAccessService $transactionAccess)
    {
    }

    public function handleCallback(Request $request)
    {
        $payload = $request->all();

        $orderId = $payload['order_id'] ?? null;
        $statusCode = $payload['status_code'] ?? null;
        $grossAmount = $payload['gross_amount'] ?? null;
        $transactionStatus = $payload['transaction_status'] ?? null;
        $fraudStatus = $payload['fraud_status'] ?? null;
        $type = $payload['payment_type'] ?? null;

        $serverKey = config('services.midtrans.server_key');
        $signature = hash("sha512", $orderId . $statusCode . $grossAmount . $serverKey);

        if ($signature !== ($payload['signature_key'] ?? '')) {
            Log::warning("Midtrans Callback: Invalid Signature untuk Order ID: {$orderId}");
            return response()->json(['message' => 'Invalid signature'], 403);
        }

        return DB::transaction(function () use ($orderId, $transactionStatus, $fraudStatus, $type, $payload) {
            $transaction = Transaction::where('midtrans_order_id', $orderId)
                ->lockForUpdate()
                ->first();

            if (!$transaction) {
                return response()->json(['message' => 'Transaction tidak ditemukan'], 404);
            }

            // source='webhook' membedakannya dari perubahan manual admin.
            $transaction->logs()->create([
                'raw_payload' => $payload,
                'source' => 'webhook',
            ]);

            // capture+challenge = masih menunggu review fraud manual, jangan
            // update status apa pun dulu.
            if ($transactionStatus === 'capture' && $fraudStatus === 'challenge') {
                Log::info("Midtrans Callback: Order {$orderId} status capture+challenge, menunggu review fraud manual.");
                return response()->json(['message' => 'Transaksi menunggu review fraud, belum diproses']);
            }

            $newStatus = match (true) {
                $transactionStatus === 'capture' => $fraudStatus === 'accept' ? 'success' : 'failed',
                $transactionStatus === 'settlement' => 'success',
                in_array($transactionStatus, ['deny', 'cancel'], true) => 'failed',
                $transactionStatus === 'expire' => 'expired',
                in_array($transactionStatus, ['refund', 'partial_refund'], true) => 'refunded',
                default => null,
            };

            if ($newStatus === null) {
                // Status lain (mis. 'pending' dari Midtrans) tidak perlu aksi.
                return response()->json(['message' => 'Status tidak memerlukan aksi']);
            }

            // IDEMPOTENCY GUARD: status baru == status lama -> webhook duplikat
            // murni, tidak ada perubahan, diabaikan.
            if ($transaction->status === $newStatus) {
                return response()->json(['message' => 'Webhook sudah diproses sebelumnya (Idempotent)']);
            }

            // Blok transisi dari status final (failed/expired/refunded), KECUALI
            // transisi valid success -> refunded (item 4).
            $terminal = ['failed', 'expired', 'refunded'];
            $isValidRefund = $transaction->status === 'success' && $newStatus === 'refunded';

            if (in_array($transaction->status, $terminal, true) && ! $isValidRefund) {
                Log::warning("Midtrans Callback: Order {$orderId} sudah final ({$transaction->status}), webhook {$newStatus} diabaikan.");
                return response()->json(['message' => 'Transaksi sudah final, webhook diabaikan']);
            }

            $transaction->update([
                'status' => $newStatus,
                'payment_method' => $type ?? $transaction->payment_method,
                'paid_at' => $newStatus === 'success' ? now() : $transaction->paid_at,
            ]);

            // Transaction::booted() otomatis mencabut/mengaktifkan enrollment
            // berdasarkan perubahan status di atas (termasuk untuk 'refunded'),
            // TAPI cuma untuk Enrollment/Subscription yang SUDAH ADA. Pemberian
            // akses untuk pembelian PERTAMA KALI (baris baru) ditangani terpisah
            // oleh TransactionAccessService -- lihat komentar di class itu.
            if ($newStatus === 'success') {
                $this->transactionAccess->grantAccessOnSuccess($transaction);

                Log::info("Midtrans Callback: akses berhasil diberikan untuk Order ID: {$orderId}");
            }

            if ($newStatus === 'refunded') {
                Log::info("Midtrans Callback: Order {$orderId} refunded, enrollment dicabut.");
            }

            return response()->json(['message' => 'Status transaksi berhasil diperbarui']);
        });
    }
}

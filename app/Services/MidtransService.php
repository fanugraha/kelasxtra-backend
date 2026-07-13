<?php

namespace App\Services;

use App\Models\Enrollment;
use App\Models\Package;
use App\Models\Promo;
use App\Models\Transaction;
use App\Models\TransactionLog;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Midtrans\Config;
use Midtrans\Snap;

class MidtransService
{
    public function __construct()
    {
        Config::$serverKey = config('services.midtrans.server_key');
        Config::$isProduction = (bool) config('services.midtrans.is_production');
        Config::$isSanitized = true;
        Config::$is3ds = true;
    }

    public function createTransaction(User $user, Package $package, ?Promo $promo = null): Transaction
    {
        $basePrice = (float) ($package->discount_price ?? $package->price);

        $discountAmount = 0;
        if ($promo) {
            $discountAmount = $promo->discount_type === 'percentage'
                ? $basePrice * ((float) $promo->discount_value / 100)
                : (float) $promo->discount_value;

            $discountAmount = min($discountAmount, $basePrice);
        }

        $amount = max($basePrice - $discountAmount, 0);
        $orderId = $this->generateOrderId($package);

        $transaction = Transaction::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'promo_id' => $promo?->id,
            'midtrans_order_id' => $orderId,
            'amount' => $amount,
            'discount_amount' => $discountAmount,
            'payment_method' => null,
            'status' => 'pending',
        ]);

        $snapToken = Snap::getSnapToken([
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => (int) round($amount),
            ],
            'customer_details' => [
                'first_name' => $user->name,
                'email' => $user->email,
            ],
            'item_details' => [
                [
                    'id' => (string) $package->id,
                    'price' => (int) round($amount),
                    'quantity' => 1,
                    'name' => Str::limit($package->name, 50, ''),
                ]
            ],
        ]);

        $transaction->setAttribute('snap_token', $snapToken);

        return $transaction;
    }

    public function handleCallback(array $payload): void
    {
        $orderId = $payload['order_id'] ?? null;

        if (!$orderId) {
            Log::warning('Midtrans callback tanpa order_id.', $payload);
            return;
        }

        $transaction = Transaction::where('midtrans_order_id', $orderId)->first();

        if (!$transaction) {
            Log::warning('Midtrans callback untuk order_id yang tidak dikenal.', ['order_id' => $orderId]);
            return;
        }

        if (!$this->isSignatureValid($payload, $transaction)) {
            Log::warning('Midtrans callback signature tidak valid.', ['order_id' => $orderId]);
            return;
        }

        TransactionLog::create([
            'transaction_id' => $transaction->id,
            'raw_payload' => $payload,
        ]);

        if (in_array($transaction->status, ['success', 'failed', 'expired'], true)) {
            return;
        }

        $newStatus = $this->mapMidtransStatus(
            $payload['transaction_status'] ?? null,
            $payload['fraud_status'] ?? null,
        );

        if ($newStatus === null) {
            return;
        }

        DB::transaction(function () use ($transaction, $newStatus, $payload) {
            $transaction->update([
                'status' => $newStatus,
                'payment_method' => $payload['payment_type'] ?? $transaction->payment_method,
                'paid_at' => $newStatus === 'success' ? now() : $transaction->paid_at,
            ]);

            if ($newStatus === 'success') {
                $this->activateEnrollment($transaction);
            }
        });
    }

    /**
     * Generate ulang Snap token untuk transaksi pending. Midtrans MENOLAK
     * generate token baru dengan order_id yang sudah pernah dipakai
     * ("order_id sudah digunakan", HTTP 400) — jadi order_id baru dibuat
     * (turunan dari yang lama + suffix waktu) dan disimpan ke transaksi yang
     * sama. Baris transaksi tetap satu (tidak ada duplikat di riwayat),
     * cuma nomor order Midtrans-nya yang berubah. Nominal (amount) yang
     * sudah didiskon dari checkout awal tetap dipakai apa adanya.
     */
    public function resumeTransaction(Transaction $transaction): string
    {
        $user = $transaction->user;
        $package = $transaction->package;
        $amount = (float) $transaction->amount;

        $newOrderId = $transaction->midtrans_order_id.'-R'.now()->format('His');
        $transaction->update(['midtrans_order_id' => $newOrderId]);

        return Snap::getSnapToken([
            'transaction_details' => [
                'order_id' => $newOrderId,
                'gross_amount' => (int) round($amount),
            ],
            'customer_details' => [
                'first_name' => $user->name,
                'email' => $user->email,
            ],
            'item_details' => [
                [
                    'id' => (string) $package->id,
                    'price' => (int) round($amount),
                    'quantity' => 1,
                    'name' => Str::limit($package->name, 50, ''),
                ]
            ],
        ]);
    }

    protected function activateEnrollment(Transaction $transaction): void
    {
        $package = $transaction->package;
        $endDate = $package->duration_days
            ? Carbon::today()->addDays($package->duration_days)
            : null;

        Enrollment::updateOrCreate(
            ['transaction_id' => $transaction->id],
            [
                'user_id' => $transaction->user_id,
                'package_id' => $transaction->package_id,
                'status' => 'active',
                'start_date' => Carbon::today(),
                'end_date' => $endDate,
            ]
        );
    }

    protected function mapMidtransStatus(?string $transactionStatus, ?string $fraudStatus): ?string
    {
        return match ($transactionStatus) {
            'capture' => $fraudStatus === 'accept' ? 'success' : ($fraudStatus === 'challenge' ? null : 'failed'),
            'settlement' => 'success',
            'deny' => 'failed',
            'cancel' => 'failed',
            'expire' => 'expired',
            'pending' => null,
            default => null,
        };
    }

    protected function isSignatureValid(array $payload, Transaction $transaction): bool
    {
        $orderId = $payload['order_id'] ?? '';
        $statusCode = $payload['status_code'] ?? '';
        $grossAmount = $payload['gross_amount'] ?? '';
        $signatureKey = $payload['signature_key'] ?? '';

        $expected = hash('sha512', $orderId.$statusCode.$grossAmount.Config::$serverKey);

        return hash_equals($expected, $signatureKey);
    }

    protected function generateOrderId(Package $package): string
    {
        return sprintf('KX-%d-%s-%s', $package->id, now()->format('YmdHis'), Str::upper(Str::random(6)));
    }
}

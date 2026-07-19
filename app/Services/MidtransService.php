<?php

namespace App\Services;

use App\Models\Package;
use App\Models\Promo;
use App\Models\Transaction;
use App\Models\User;
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

        // Pakai Promo::calculateDiscount() (bukan hitung ulang di sini) supaya
        // aturan cap max_discount_amount ikut diterapkan konsisten dengan
        // yang ditampilkan ke user pas validateCode().
        $discountAmount = $promo ? $promo->calculateDiscount($package) : 0;

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

    protected function generateOrderId(Package $package): string
    {
        return sprintf('KX-%d-%s-%s', $package->id, now()->format('YmdHis'), Str::upper(Str::random(6)));
    }
}

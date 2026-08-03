<?php

namespace App\Services;

use App\Models\Package;
use App\Models\Promo;
use App\Models\Transaction;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Midtrans\Config;
use Midtrans\Snap;

class MidtransService
{
    // Snap VA/QRIS default kedaluwarsa 24 jam. Kalau config expiry Snap
    // diubah di dashboard/kode lain, sesuaikan juga nilai ini.
    protected const SNAP_EXPIRY_HOURS = 24;

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

        $discountAmount = $promo ? $promo->calculateDiscount($package) : 0;

        $amount = max($basePrice - $discountAmount, 0);
        $orderId = $this->generateOrderId($package);

        $transaction = Transaction::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'promo_id' => $promo?->id,
            'midtrans_order_id' => $orderId,
            'invoice_number' => $this->generateInvoiceNumber(),
            'amount' => $amount,
            'discount_amount' => $discountAmount,
            'payment_method' => null,
            'status' => 'pending',
            'expires_at' => now()->addHours(self::SNAP_EXPIRY_HOURS),
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
     * Generate ulang Snap token untuk transaksi pending (dipakai baik untuk
     * tombol "Lanjutkan Pembayaran" maupun saat checkout() mendeteksi masih
     * ada transaksi pending untuk paket yang sama).
     *
     * order_id baru diturunkan dari order_id ASLI (suffix "-R{HHmmss}" lama
     * dilucuti dulu sebelum ditambah suffix baru) supaya tidak menumpuk
     * "-R111111-R222222-R333333..." tiap kali di-resume berulang kali.
     *
     * Promo divalidasi ulang: kalau sudah tidak valid lagi (kuota habis /
     * kedaluwarsa), transaksi tetap dilanjutkan tapi dengan harga normal
     * (promo dilepas dari transaksi). Keputusan ini bisa direvisi kalau
     * bisnis mau perilaku lain (menolak resume & minta checkout ulang).
     */
    public function resumeTransaction(Transaction $transaction): string
    {
        $user = $transaction->user;
        // BUG FIX: sebelumnya method ini cuma pernah ditulis/ditest untuk
        // transaksi package ($transaction->package langsung dipakai tanpa
        // cek null) -- transaksi subscription (plan_id terisi, package_id
        // null) bikin $package null dan meledak 500 di
        // $package->id/$package->name. item bisa Package ATAU
        // SubscriptionPlan, sama seperti pola di Promo::checkUsableBy/
        // calculateDiscount.
        $item = $transaction->package ?: $transaction->plan;
        $amount = (float) $transaction->amount;

        if ($transaction->promo_id && $transaction->promo && $item) {
            $error = $transaction->promo->checkUsableBy($user, $item);

            if ($error) {
                $amount = $item instanceof Package
                    ? (float) ($item->discount_price ?? $item->price)
                    : (float) $item->price;

                $transaction->update([
                    'promo_id' => null,
                    'discount_amount' => 0,
                    'amount' => $amount,
                ]);

                Log::warning("Resume order {$transaction->midtrans_order_id}: promo sudah tidak valid ({$error}), dilanjutkan dengan harga normal.");
            }
        }

        $baseOrderId = preg_replace('/-R\d{6}$/', '', $transaction->midtrans_order_id);
        $newOrderId = $baseOrderId.'-R'.now()->format('His');

        $transaction->update([
            'midtrans_order_id' => $newOrderId,
            'expires_at' => now()->addHours(self::SNAP_EXPIRY_HOURS),
        ]);

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
                    'id' => $item instanceof Package ? (string) $item->id : 'plan-'.$item->id,
                    'price' => (int) round($amount),
                    'quantity' => 1,
                    'name' => Str::limit($item->name, 50, ''),
                ]
            ],
        ]);
    }

    protected function generateOrderId(Package $package): string
    {
        return sprintf('KX-%d-%s-%s', $package->id, now()->format('YmdHis'), Str::upper(Str::random(6)));
    }

    /**
     * Format: INV-{YYYYMMDD}-{4 digit sequence per hari}.
     * Dipanggil di dalam DB transaction checkout() yang sudah lockForUpdate
     * pada tabel promos; kalau nanti ada race condition di volume tinggi,
     * pertimbangkan lockForUpdate() eksplisit di query di bawah juga.
     */
    protected function generateInvoiceNumber(): string
    {
        $prefix = 'INV-'.now()->format('Ymd').'-';

        $lastNumber = Transaction::where('invoice_number', 'like', "{$prefix}%")
            ->lockForUpdate()
            ->orderByDesc('invoice_number')
            ->value('invoice_number');

        $sequence = $lastNumber ? ((int) substr($lastNumber, -4)) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    public function createSubscriptionTransaction(User $user, SubscriptionPlan $plan, array $selectedProgramIds = [], ?Promo $promo = null): Transaction
    {
        $basePrice = (float) $plan->price;

        $discountAmount = $promo ? $promo->calculateDiscount($plan) : 0;

        $amount = max($basePrice - $discountAmount, 0);
        $orderId = $this->generatePlanOrderId($plan);

        $transaction = Transaction::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'promo_id' => $promo?->id,
            'selected_program_ids' => $plan->program_slot_count ? $selectedProgramIds : null,
            'midtrans_order_id' => $orderId,
            'invoice_number' => $this->generateInvoiceNumber(),
            'amount' => $amount,
            'discount_amount' => $discountAmount,
            'payment_method' => null,
            'status' => 'pending',
            'expires_at' => now()->addHours(self::SNAP_EXPIRY_HOURS),
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
                    'id' => 'plan-'.$plan->id,
                    'price' => (int) round($amount),
                    'quantity' => 1,
                    'name' => Str::limit($plan->name, 50, ''),
                ]
            ],
        ]);

        $transaction->setAttribute('snap_token', $snapToken);

        return $transaction;
    }

    protected function generatePlanOrderId(SubscriptionPlan $plan): string
    {
        return sprintf('KX-SUB-%d-%s-%s', $plan->id, now()->format('YmdHis'), Str::upper(Str::random(6)));
    }
}

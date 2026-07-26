<?php

namespace App\Services;

use App\Models\Enrollment;
use App\Models\Subscription;
use App\Models\Transaction;
use Illuminate\Support\Facades\Log;

/**
 * Satu-satunya jalur resmi untuk memberi akses (Enrollment / Subscription)
 * begitu sebuah Transaction dinyatakan 'success' -- dipakai bareng oleh
 * MidtransCallbackController::handleCallback() (webhook real-time) DAN
 * ReconcilePendingTransactions (job cadangan tiap 30 menit untuk transaksi
 * yang webhook-nya gagal/telat terkirim).
 *
 * SEBELUM diekstrak ke sini: reconcile command cuma memanggil
 * $transaction->update(['status' => 'success', ...]), yang men-trigger
 * Transaction::booted() -- tapi hook itu HANYA mengubah status
 * Enrollment/Subscription yang SUDAH ADA, tidak pernah membuat baris baru.
 * Akibatnya pembelian PERTAMA KALI yang baru ke-granted lewat reconcile
 * (bukan webhook) tidak pernah dapat akses sama sekali, walau transaksinya
 * sudah tercatat 'success' di database -- siswa sudah bayar tapi tidak
 * pernah bisa membuka paketnya. Sekarang kedua caller wajib panggil method
 * ini supaya pemberian akses konsisten di kedua jalur.
 */
class TransactionAccessService
{
    public function grantAccessOnSuccess(Transaction $transaction): void
    {
        if ($transaction->package_id) {
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

            Log::info("TransactionAccessService: Enrollment aktif untuk transaksi #{$transaction->id} (order {$transaction->midtrans_order_id}).");
        }

        if ($transaction->plan_id) {
            $plan = $transaction->plan;

            $programIds = $plan->program_slot_count
                ? ($transaction->selected_program_ids ?? [])
                : [$plan->program_id];

            $subscription = Subscription::updateOrCreate(
                [
                    'user_id' => $transaction->user_id,
                    'plan_id' => $transaction->plan_id,
                ],
                [
                    'transaction_id' => $transaction->id,
                    'status' => 'active',
                    'start_date' => now(),
                    'end_date' => $plan->duration_days
                        ? now()->addDays($plan->duration_days)
                        : null,
                ]
            );

            $subscription->programs()->sync($programIds);

            Log::info("TransactionAccessService: Subscription aktif untuk transaksi #{$transaction->id} (order {$transaction->midtrans_order_id}).");
        }
    }
}

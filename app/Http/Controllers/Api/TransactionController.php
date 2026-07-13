<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\Promo;
use App\Models\Transaction;
use App\Services\MidtransService;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function __construct(protected MidtransService $midtrans)
    {
    }

    /**
     * POST /api/transactions/checkout
     * Body: package_id, promo_code (opsional)
     * Selalu bikin row transaksi baru (histori "Riwayat Transaksi" tetap lengkap
     * kalau siswa retry checkout, sesuai section 6 poin 6).
     */
    public function checkout(Request $request)
    {
        $data = $request->validate([
            'package_id' => ['required', 'integer', 'exists:packages,id'],
            'promo_code' => ['nullable', 'string'],
        ]);

        $package = Package::findOrFail($data['package_id']);

        $promo = null;
        if (! empty($data['promo_code'])) {
            $promo = Promo::where('code', $data['promo_code'])->first();

            if (! $promo) {
                return response()->json(['message' => 'Kode promo tidak ditemukan.'], 404);
            }

            if (now()->toDateString() > $promo->valid_until->toDateString()) {
                return response()->json(['message' => 'Kode promo sudah kedaluwarsa.'], 422);
            }
        }

        $transaction = $this->midtrans->createTransaction($request->user(), $package, $promo);

        return response()->json($transaction, 201);
    }

    /**
     * GET /api/transactions
     * Untuk halaman "Riwayat Transaksi" siswa (section 6 poin 6) — retry
     * checkout kalau status pending/failed/expired.
     */
    public function index(Request $request)
    {
        return $request->user()->transactions()->latest()->get();
    }

    /**
     * POST /api/midtrans/callback
     * Webhook Midtrans (bisa terkirim >1x, section 6 poin 4). Route publik,
     * keamanan lewat signature verification (hash_equals) di dalam
     * MidtransService::handleCallback().
     */
    public function callback(Request $request)
    {
        $this->midtrans->handleCallback($request->all());

        return response()->json(['message' => 'OK']);
    }

    /**
     * GET /api/transactions/{transaction}
     * Polling status transaksi tunggal (webhook Midtrans tidak instan,
     * frontend perlu cara cek "sudah lunas belum" setelah checkout).
     */
    public function show(Request $request, Transaction $transaction)
    {
        abort_unless($transaction->user_id === $request->user()->id, 403, 'Bukan transaksi milik Anda.');

        return $transaction->load('package', 'promo');
    }

    /**
     * POST /api/transactions/{transaction}/resume
     * Generate ulang Snap token untuk transaksi pending — dipakai tombol
     * "Lanjutkan Pembayaran" di halaman status transaksi. order_id dipakai
     * ulang (bukan bikin transaksi baru), Midtrans izinkan ini selama status
     * transaksi masih pending di sisi mereka.
     */
    public function resume(Request $request, Transaction $transaction)
    {
        abort_unless($transaction->user_id === $request->user()->id, 403, 'Bukan transaksi milik Anda.');
        abort_unless($transaction->status === 'pending', 422, 'Transaksi ini sudah tidak bisa dilanjutkan.');

        return response()->json([
            'id' => $transaction->id,
            'snap_token' => $this->midtrans->resumeTransaction($transaction),
        ]);
    }
}

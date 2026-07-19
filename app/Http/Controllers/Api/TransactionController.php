<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\Promo;
use App\Models\Transaction;
use App\Services\MidtransService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
     *
     * Promo dicek ulang di sini (bukan cuma di validateCode()) karena kondisi
     * bisa berubah di antara user validate kode dan user beneran checkout.
     * lockForUpdate() dipakai supaya 2 checkout bersamaan di detik terakhir
     * kuota promo tidak sama-sama lolos.
     */
    public function checkout(Request $request)
    {
        $data = $request->validate([
            'package_id' => ['required', 'integer', 'exists:packages,id'],
            'promo_code' => ['nullable', 'string'],
        ]);

        $package = Package::findOrFail($data['package_id']);
        $user = $request->user();

        // Cegah beli ulang paket yang sudah pernah lunas — sekali sukses,
        // paket itu terkunci selamanya untuk user ini, walau duration_days
        // sudah lewat. Tidak ada logic re-purchase setelah kadaluarsa.
        $alreadyOwned = $user->transactions()
            ->where('package_id', $package->id)
            ->where('status', 'success')
            ->exists();

        if ($alreadyOwned) {
            abort(422, 'Kamu sudah memiliki paket ini.');
        }

        $transaction = DB::transaction(function () use ($data, $package, $user) {
            $promo = null;

            if (! empty($data['promo_code'])) {
                $promo = Promo::where('code', $data['promo_code'])->lockForUpdate()->first();

                if (! $promo) {
                    abort(404, 'Kode promo tidak ditemukan.');
                }

                $error = $promo->checkUsableBy($user, $package);
                if ($error) {
                    abort(422, $error);
                }
            }

            return $this->midtrans->createTransaction($user, $package, $promo);
        });

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
     * "Lanjutkan Pembayaran" di halaman status transaksi.
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

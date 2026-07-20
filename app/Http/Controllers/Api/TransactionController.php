<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
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
     */
    public function checkout(Request $request)
    {
        $data = $request->validate([
            'package_id' => ['required', 'integer', 'exists:packages,id'],
            'promo_code' => ['nullable', 'string'],
        ]);

        $package = Package::findOrFail($data['package_id']);
        $user = $request->user();

        $result = DB::transaction(function () use ($data, $package, $user) {
            // Item 1: cegah transaksi pending ganda untuk paket yang sama.
            // Midtrans menolak generate token baru untuk order_id yang sama,
            // jadi kalau ada transaksi pending, kita resume transaksi itu
            // (dapat order_id + token baru) alih-alih bikin baris baru.
            $existingPending = Transaction::where('user_id', $user->id)
                ->where('package_id', $package->id)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->latest()
                ->first();

            if ($existingPending) {
                return [
                    'transaction' => $existingPending,
                    'snap_token' => $this->midtrans->resumeTransaction($existingPending),
                    'is_new' => false,
                ];
            }

            // Item 2: cek kepemilikan lewat Enrollment, bukan histori
            // transaksi sukses. Paket lifetime (duration_days null) terkunci
            // selamanya begitu punya enrollment. Paket berdurasi boleh dibeli
            // ulang kalau enrollment-nya sudah tidak aktif lagi.
            $enrollment = Enrollment::where('user_id', $user->id)
                ->where('package_id', $package->id)
                ->first();

            if ($enrollment) {
                $isLifetime = $package->duration_days === null;
                $isStillActive = $enrollment->status === 'active'
                    && (! $enrollment->end_date || $enrollment->end_date->isFuture());

                if ($isLifetime || $isStillActive) {
                    abort(422, 'Kamu sudah memiliki paket ini.');
                }
            }

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

            $transaction = $this->midtrans->createTransaction($user, $package, $promo);

            return [
                'transaction' => $transaction,
                'snap_token' => $transaction->snap_token,
                'is_new' => true,
            ];
        });

        $transaction = $result['transaction'];
        $transaction->setAttribute('snap_token', $result['snap_token']);

        return response()->json($transaction, $result['is_new'] ? 201 : 200);
    }

    /**
     * GET /api/transactions
     */
    public function index(Request $request)
    {
        return $request->user()->transactions()->latest()->get();
    }

    /**
     * GET /api/transactions/{transaction}
     */
    public function show(Request $request, Transaction $transaction)
    {
        abort_unless($transaction->user_id === $request->user()->id, 403, 'Bukan transaksi milik Anda.');

        return $transaction->load('package', 'promo');
    }

    /**
     * POST /api/transactions/{transaction}/resume
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

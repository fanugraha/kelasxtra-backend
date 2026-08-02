<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Package;
use App\Models\Promo;
use App\Models\SubscriptionPlan;
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
            'package_id' => ['required_without:plan_id', 'nullable', 'integer', 'exists:packages,id'],
            'plan_id' => ['required_without:package_id', 'nullable', 'integer', 'exists:subscription_plans,id'],
            'promo_code' => ['nullable', 'string'],
            'program_ids' => ['nullable', 'array'],
            'program_ids.*' => ['integer', 'exists:programs,id'],
        ]);

        if (! empty($data['plan_id'])) {
            return $this->checkoutSubscription($data, $request->user());
        }

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
     * Eager-load package/plan/promo -- riwayat transaksi (mobile & web) perlu
     * nama paket/plan buat ditampilkan di list, bukan cuma package_id/plan_id
     * mentah. Sebelumnya tidak di-eager-load sama sekali (N+1 kalau frontend
     * coba akses transaction.package.name, atau tampil kosong).
     */
    public function index(Request $request)
    {
        return $request->user()->transactions()->with(['package', 'plan', 'promo'])->latest()->get();
    }

    /**
     * GET /api/transactions/{transaction}
     * `plan` ditambahkan ke load() -- sebelumnya cuma package+promo, jadi
     * transaksi subscription (plan_id terisi, package_id null) detailnya
     * tidak pernah menampilkan nama plan.
     */
    public function show(Request $request, Transaction $transaction)
    {
        abort_unless($transaction->user_id === $request->user()->id, 403, 'Bukan transaksi milik Anda.');

        return $transaction->load('package', 'plan', 'promo');
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

    protected function checkoutSubscription(array $data, $user)
    {
        $plan = SubscriptionPlan::findOrFail($data['plan_id']);

        $result = DB::transaction(function () use ($data, $plan, $user) {
            $existingPending = Transaction::where('user_id', $user->id)
                ->where('plan_id', $plan->id)
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

            if ($plan->program_slot_count) {
                $programIds = $data['program_ids'] ?? [];

                if (count($programIds) !== $plan->program_slot_count) {
                    abort(422, "Plan ini butuh tepat {$plan->program_slot_count} program dipilih.");
                }
            } else {
                if (blank($plan->program_id)) {
                    abort(500, 'Plan tidak valid: tidak fix ke program manapun dan tidak punya slot count.');
                }

                $programIds = [$plan->program_id];
            }

            $alreadyCovered = $user->subscriptions()->active()->get()
                ->contains(fn ($sub) => collect($programIds)->every(fn ($pid) => $sub->coversProgram($pid)));

            if ($alreadyCovered) {
                abort(422, 'Kamu sudah punya langganan aktif yang mencakup program ini.');
            }

            $promo = null;

            if (! empty($data['promo_code'])) {
                $promo = Promo::where('code', $data['promo_code'])->lockForUpdate()->first();

                if (! $promo) {
                    abort(404, 'Kode promo tidak ditemukan.');
                }

                $error = $promo->checkUsableBy($user, $plan);
                if ($error) {
                    abort(422, $error);
                }
            }

            $transaction = $this->midtrans->createSubscriptionTransaction($user, $plan, $programIds, $promo);

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
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\Promo;
use Illuminate\Http\Request;

class PromoController extends Controller
{
    /**
     * GET /api/promos/active
     * Promo yang belum kedaluwarsa, buat ditampilkan di banner katalog.
     */
    public function active(Request $request)
    {
        return Promo::where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('valid_until')
                    ->orWhere('valid_until', '>=', now()->toDateString());
            })
            ->where(function ($query) {
                $query->whereNull('valid_from')
                    ->orWhere('valid_from', '<=', now());
            })
            ->orderByRaw('valid_until IS NULL, valid_until ASC')
            ->get();
    }

    /**
     * POST /api/promos/validate
     * Body: code, package_id
     * Dipakai tombol "Terapkan" di modal checkout — cek kode valid & belum
     * kedaluwarsa, lalu hitung potongan harga terhadap harga final paket
     * (discount_price kalau ada, else price). Tidak membuat transaksi apa pun,
     * murni pre-check sebelum checkout beneran.
     */
    public function validateCode(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string'],
            'package_id' => ['required', 'integer', 'exists:packages,id'],
        ]);

        $promo = Promo::where('code', $data['code'])->first();

        if (!$promo) {
            return response()->json(['message' => 'Kode promo tidak ditemukan.'], 404);
        }

        $package = Package::findOrFail($data['package_id']);
        $user = $request->user();

        // Pakai aturan yang sama dengan TransactionController::checkout(),
        // supaya kode yang lolos di sini pasti juga lolos pas checkout
        // beneran (termasuk new_user_only, usage_limit_per_user, total_quota,
        // dan applicable_package_id — sebelumnya cuma valid_until yang dicek).
        $error = $promo->checkUsableBy($user, $package);
        if ($error) {
            return response()->json(['message' => $error], 422);
        }

        $basePrice = (float) ($package->discount_price ?? $package->price);
        $discountAmount = $promo->calculateDiscount($package);
        $finalAmount = max($basePrice - $discountAmount, 0);

        return response()->json([
            'promo' => $promo,
            'base_price' => $basePrice,
            'discount_amount' => $discountAmount,
            'final_amount' => round($finalAmount, 2),
        ]);
    }
}

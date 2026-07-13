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
        return Promo::where('valid_until', '>=', now()->toDateString())
            ->orderBy('valid_until')
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

        if (! $promo) {
            return response()->json(['message' => 'Kode promo tidak ditemukan.'], 404);
        }

        if (now()->toDateString() > $promo->valid_until->toDateString()) {
            return response()->json(['message' => 'Kode promo sudah kedaluwarsa.'], 422);
        }

        $package = Package::findOrFail($data['package_id']);
        $basePrice = (float) ($package->discount_price ?? $package->price);

        $discountAmount = $promo->discount_type === 'percentage'
            ? $basePrice * ((float) $promo->discount_value / 100)
            : (float) $promo->discount_value;

        // Potongan tidak boleh melebihi harga paket itu sendiri.
        $discountAmount = min($discountAmount, $basePrice);
        $finalAmount = max($basePrice - $discountAmount, 0);

        return response()->json([
            'promo' => $promo,
            'base_price' => $basePrice,
            'discount_amount' => round($discountAmount, 2),
            'final_amount' => round($finalAmount, 2),
        ]);
    }
}

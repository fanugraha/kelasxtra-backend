<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Package;
use Illuminate\Http\Request;

class PackageController extends Controller
{
    /**
     * GET /api/packages
     * Daftar semua paket yang bisa dibeli siswa. Filter opsional ?program_id=.
     */
    public function index(Request $request)
    {
        return Package::with('program', 'subject')
            ->when($request->filled('program_id'), fn ($q) => $q->where('program_id', $request->program_id))
            ->latest()
            ->get();
    }

    /**
     * GET /api/packages/recommended
     * Rekomendasi Beranda: berdasarkan program yang paling sering dibeli user
     * (dari transaksi sukses). User baru / belum pernah beli -> fallback ke
     * paket terbaru. Paket yang sudah dimiliki (enrollment) disingkirkan.
     */
    public function recommended(Request $request)
    {
        $user = $request->user();

        // Guest (belum login, misal dari landing page publik) -> nggak punya
        // enrollment/transaksi buat dipersonalisasi, langsung ke paket terbaru.
        if (! $user) {
            $packages = Package::with('program', 'subject')->latest()->take(8)->get();

            return response()->json([
                'based_on_program_id' => null,
                'packages' => $packages,
            ]);
        }

        $ownedPackageIds = $user->enrollments()->pluck('package_id');

        $topProgramId = $user->transactions()
            ->where('status', 'success')
            ->join('packages', 'packages.id', '=', 'transactions.package_id')
            ->whereNotNull('packages.program_id')
            ->selectRaw('packages.program_id, COUNT(*) as total')
            ->groupBy('packages.program_id')
            ->orderByDesc('total')
            ->value('packages.program_id');

        $query = Package::with('program', 'subject')->whereNotIn('id', $ownedPackageIds);

        if ($topProgramId) {
            $query->where('program_id', $topProgramId);
        }

        $packages = $query->latest()->take(8)->get();

        // Fallback kalau rekomendasi program kosong.
        if ($packages->isEmpty()) {
            $packages = Package::with('program', 'subject')
                ->whereNotIn('id', $ownedPackageIds)
                ->latest()
                ->take(8)
                ->get();
        }

        return response()->json([
            'based_on_program_id' => $topProgramId,
            'packages' => $packages,
        ]);
    }

    /**
     * GET /api/packages/{package}
     * Detail satu paket.
     */
    public function show(Package $package)
    {
        return $package->load('program', 'subject');
    }
}

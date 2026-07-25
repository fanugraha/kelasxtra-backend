<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class EnrollmentController extends Controller
{
    /**
     * GET /api/my-packages
     * Daftar paket yang sudah dibeli siswa (enrollment aktif maupun yang
     * sudah kedaluwarsa) -- untuk halaman "Paket Belajar Saya". Package.classes
     * di-eager-load supaya frontend bisa langsung dapat class_id untuk paket
     * kelas online tanpa request tambahan.
     *
     * CATATAN: Latihan Soal per Part TIDAK muncul di sini -- itu bukan
     * "dimiliki" lewat Package, tapi katalog terbuka dengan gerbang per-Part
     * (Part 1 gratis, Part 2+ butuh Subscription aktif). Lihat
     * TopicPracticeController untuk endpoint Latihan Soal.
     */
    public function index(Request $request)
    {
        return $request->user()->enrollments()
            ->with('package.classes')
            ->latest()
            ->get()
            ->map(fn ($enrollment) => [
                'id' => $enrollment->id,
                'package' => $enrollment->package,
                'status' => $enrollment->status,
                'is_active' => $enrollment->isActive(),
                'start_date' => $enrollment->start_date,
                'end_date' => $enrollment->end_date,
            ]);
    }
}

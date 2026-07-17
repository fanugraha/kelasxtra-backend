<?php

use App\Http\Controllers\PublicController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes — Halaman publik SEO-first (Blade, server-rendered)
|--------------------------------------------------------------------------
| React app (login, dashboard, exam, checkout, dll) mounted terpisah di
| /app/* dan route publik lain (/login, /daftar, dst) — itu semua di-serve
| oleh index.html hasil build React (lihat catatan deploy), BUKAN lewat
| Laravel router ini. Route di sini KHUSUS untuk halaman yang perlu SEO.
*/

Route::get('/', [PublicController::class, 'landing'])->name('landing');
Route::get('/artikel', [PublicController::class, 'articles'])->name('articles.index');
Route::get('/artikel/{slug}', [PublicController::class, 'articleDetail'])->name('articles.show');

/*
|--------------------------------------------------------------------------
| Catch-all -> React SPA (Skenario A: React di-build lalu di-copy ke
| public/app/ pada tiap deploy — lihat DEPLOY_NOTES.md).
|--------------------------------------------------------------------------
| Menangani /login, /daftar, /cek-email, /lupa-password, /reset-password,
| dan semua /app/* (dashboard, exam, checkout, dst). Route publik/SEO di
| atas (/, /artikel, /artikel/{slug}) sudah match duluan karena didaftarkan
| lebih dulu, jadi tidak akan "ketelan" oleh catch-all ini.
*/
Route::get('/{any}', function () {
    $indexPath = public_path('app/index.html');

    abort_unless(file_exists($indexPath), 404, 'Build frontend React belum di-copy ke public/app. Jalankan build & copy dulu (lihat DEPLOY_NOTES.md).');

    return response()->file($indexPath);
})->where('any', '.*');

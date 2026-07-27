# Kelasxtra Backend

Backend Laravel untuk **Kelasxtra** — platform belajar & try out online. Saat ini fokus utama melayani **CPNS 2026**, dengan arsitektur yang sudah disiapkan untuk ekspansi ke segmen Sekolah/SNBT-UTBK di masa depan.

Untuk keputusan arsitektur & alasan di baliknya (kenapa skema dibuat begini, apa yang masih ditunda dan kenapa), lihat **[docs/ARCHITECTURE_DECISIONS.md](docs/ARCHITECTURE_DECISIONS.md)**.

## Stack

- **Framework**: Laravel 13, PHP 8.3+
- **Admin panel**: Filament 5
- **Auth**: Laravel Sanctum (API token, dipakai frontend React)
- **Payment**: Midtrans Snap (`midtrans/midtrans-php`)
- **Database**: MySQL (production), SQLite (default lokal/testing)
- **Testing**: PHPUnit via `php artisan test`

## Setup Lokal

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed   # opsional, kalau ada seeder yang dibutuhkan
php artisan serve
```

Jalankan test suite sebelum push apapun:

```bash
php artisan test
```

## Struktur Singkat

- `app/Services/` — logic inti: `AccessControlService` (siapa boleh akses apa), `ExamScoringService` (penilaian), `TopicMasteryService` (analisis kelemahan per topik)
- `app/Models/Program.php` — pembeda mode bisnis lewat `question_grouping_mode` (`category` = CPNS/BUMN, `subject` = Sekolah/SNBT), lihat docs untuk detail
- `app/Filament/` — admin panel (soal, paket, transaksi, dll)
- `routes/api.php` — semua endpoint yang dikonsumsi frontend React (`kelasxtra-frontend`)

## Deploy

Deploy manual ke VPS (`kelasxtra` user):

```bash
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:clear && php artisan cache:clear && php artisan queue:restart
```

## Status Versi

**Versi saat ini (v1 — CPNS)**: exam engine lengkap (timer per section, passing grade, leaderboard), payment Midtrans (one-time + subscription), analisis kekuatan/kelemahan per topik dengan histori tren.

**Rencana ke depan (v2 — Sekolah/SNBT)**: program mode `subject` untuk materi per-mapel, target belajar personal (`learning_goals`, sudah di-scaffold), dashboard multi-anak untuk orang tua (`users.parent_id`, sudah di-scaffold), rasionalisasi masuk kampus (belum didesain).

Detail lengkap tiap keputusan ada di `docs/ARCHITECTURE_DECISIONS.md`.

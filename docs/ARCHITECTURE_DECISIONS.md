# Catatan Keputusan Arsitektur & Roadmap

Dokumen ini nyimpen alasan di balik keputusan teknis penting di codebase ini, supaya kalau nanti (kamu sendiri atau developer lain) buka kode dan bingung "kok begini?", jawabannya ada di sini — bukan harus nebak atau nanya lagi dari nol.

Terakhir diperbarui: 27 Juli 2026.

---

## 1. Ringkasan Status

Per Juli 2026, seluruh utang teknis dari analisa arsitektur awal sudah dituntaskan. Checklist di bawah ini dipertahankan sebagai log historis, bukan to-do list aktif.

| Prioritas | Item | Status |
|---|---|---|
| P0 | `hasFullPerformanceAccess()` konsisten dengan `Subscription::coversProgram()` | ✅ Selesai |
| P0 | Keputusan produk: akses exam focus-topic lewat Enrollment atau Subscription saja | ✅ Diputuskan — **Subscription saja**, eksklusif |
| P1 | Retire tabel/model `categories`/`subjects` + kolom `legacy_*` | ✅ Selesai |
| P1 | Unifikasi `scoring_type` (dulu dobel di `question_banks` & `exam_sections`) | ✅ Selesai |
| P2 | `exams.context` (`tryout` \| `topic_practice`) sebagai discriminator eksplisit | ✅ Selesai |
| P2 | `TopicMasteryService` + tabel rollup `topic_mastery_snapshots` (write + read path) | ✅ Selesai |
| P3 | Relasi orang tua-anak (`users.parent_id`, role `orang_tua`) | ✅ Scaffolding selesai, **belum ada endpoint/UI** |
| P3 | Target belajar (`learning_goals`) | ✅ Scaffolding selesai, **belum ada endpoint/UI** |
| P3 | Rekomendasi belajar | ✅ Diputuskan — **tidak butuh tabel**, dihitung on-the-fly |

---

## 2. Dua Mode Bisnis dalam Satu Codebase

Kelasxtra dirancang untuk melayani dua jenis bisnis lewat satu platform, dibedakan lewat `programs.question_grouping_mode`:

| Mode | Contoh Brand | Karakteristik |
|---|---|---|
| `category` | CPNS, BUMN, Kedinasan | Banyak Bagian Ujian sekaligus per exam (TWK/TIU/TKP). Kelulusan = passing grade nasional yang **fixed**, sama untuk semua peserta. |
| `subject` | Sekolah, Masuk Kuliah (SNBT/UTBK) | Latihan per Mapel, satu-satu, tidak digabung jadi paket ujian. Kelulusan = **kompetitif** (peluang lolos kampus/prodi tertentu), personal per siswa. |

**Kenapa ini penting dipahami sebelum bikin fitur baru**: banyak keputusan skema di bawah ini gate-nya pakai field ini, bukan bikin tabel/kolom terpisah untuk tiap brand. Sebelum menambah fitur, cek dulu apakah fitur itu relevan untuk `category`, `subject`, atau keduanya — jangan asumsikan berlaku untuk semua program.

Program sekarang juga punya `brand_id` (FK ke tabel `brands`) untuk pengelompokan di level brand/tenant, terpisah dari `question_grouping_mode` yang soal *cara ujian dikelompokkan*.

---

## 3. Kenapa Target Belajar (`learning_goals`) Di-gate, Bukan Di-drop Total

Riset benchmark ke Ruangguru/Fahamify: fitur seperti "Rasionalisasi SNBT" itu berupa target skor personal dibandingkan standar kompetitif (peluang lolos kampus X). Fitur ini **make sense untuk mode `subject`**, tapi **tidak relevan untuk mode `category`** — CPNS punya passing grade nasional yang sama untuk semua orang, jadi "target personal" tidak menambah nilai apa pun buat siswa CPNS.

Keputusan: tabel `learning_goals` dibuat (lihat `app/Models/LearningGoal.php`), tapi guard "hanya untuk program mode `subject`" ditegakkan di **application layer** (service/controller), bukan lewat `CHECK` constraint di database. Alasan: kalau nanti kebutuhan berubah (misal CPNS ternyata butuh target skor custom per section di atas passing grade), tinggal ubah logic guard-nya — tidak perlu migrasi ulang skema.

**Kalau kamu mau bangun fitur di atas ini**: cek `$program->usesSubjectMode()` sebelum expose create/read endpoint untuk `LearningGoal`.

---

## 4. Kenapa Rekomendasi Belajar Tidak Punya Tabel

Pola yang sama ditemukan di semua platform pembanding: rekomendasi belajar dihitung dari hasil tryout/analisis performa yang sudah ada, bukan disimpan sebagai entitas independen. Kelasxtra sudah punya bahan mentahnya lewat `TopicMasteryService` (kategori weak/medium/strong per topik, tren antar periode).

Keputusan: **tidak ada tabel `recommendations`**. Endpoint rekomendasi dihitung on-the-fly dari `topic_mastery_snapshots` + `TopicPerformanceService` setiap kali diakses. Kalau nanti butuh tracking ("rekomendasi apa yang sudah ditampilkan/diklik" untuk analytics), baru pertimbangkan tabel log ringan (`recommendation_impressions`) — bukan tabel rekomendasi itu sendiri.

---

## 5. Relasi Orang Tua-Anak

Keputusan produk: 1 orang tua bisa pantau banyak anak, 1 anak cukup 1 orang tua. Ini cukup pakai foreign key self-referential (`users.parent_id`), **tidak perlu tabel pivot**.

Role `orang_tua` sudah ditambahkan ke enum `users.role`, supaya orang tua bisa login lewat akun sendiri (bukan sekadar "viewer mode" di akun anak).

"Kebutuhan anak" (SD/SMP/SMA, fokus ke TIU, dst) **bukan** atribut relasi orang tua-anak — itu ditentukan lewat program apa yang anak ikuti (`enrollments`/`subscriptions` yang sudah ada), jadi tidak perlu tabel tambahan untuk itu.

Status: skema sudah ada (`User::parent()`, `User::children()`), **belum ada endpoint/controller/UI**. Ini scaffolding murni untuk fitur dashboard multi-anak yang belum dikerjakan.

---

## 6. Kenapa Welcome Screen "Pilih Level Pendidikan" Belum Dibuat

`users.level_pendidikan` (enum `sd/smp/sma/mahasiswa/umum`) sudah ada sejak awal, tapi cuma dipakai untuk badge di leaderboard — **belum jadi discriminator konten** seperti di Pahamify/Ruangguru, karena Kelasxtra belum punya program mode `subject` yang granular per jenjang.

Keputusan: welcome screen wajib isi level ditunda sampai program mode `subject` beneran diluncurkan. Memaksa user isi data yang belum ada gunanya cuma nambah friksi onboarding tanpa payoff. Saat brand Sekolah/SNBT jalan, kemungkinan besar enum ini juga perlu diperluas (per kelas, bukan cuma per jenjang) — evaluasi ulang di titik itu, jangan asumsikan enum sekarang cukup.

---

## 7. Yang Masih Perlu Keputusan Produk (Belum Dikerjakan)

- **Rasionalisasi SNBT/UTBK** (peluang lolos kampus berdasarkan skor) — butuh dataset standar nilai masuk PTN, kompleks, belum didesain sama sekali.
- **Enum level pendidikan granular per kelas** (bukan cuma sd/smp/sma) — relevan begitu program `subject` mode mulai dibangun.
- **Tracking histori rekomendasi** (kalau nanti butuh analytics klik/impresi) — lihat poin 4.

Kalau mengerjakan salah satu di atas, mulai dari diskusi produk dulu (siapa target user, apa yang mau diukur), baru desain skema — jangan sebaliknya.

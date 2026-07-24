# TODO — Kelasxtra Backend

## Fitur "Latihan Soal per Topik" (belum dibangun)

**Konteks:** Ditemukan saat implementasi fitur Analisis Kekuatan & Kelemahan
(`/me/performance-summary`, `PerformanceController`). Setiap topik lemah di
`top_recommendations[]` seharusnya punya `practice_link` yang mengarah
langsung ke sesi latihan soal khusus topik itu (mis. "Latihan 10 soal Pilar
Negara"), tapi fitur ini belum ada sama sekali di aplikasi.

**Solusi sementara (24 Juli 2026):** `practice_link` diarahkan ke
`/app/packages?program_id={id}` -- generik, tidak memfilter ke topik
spesifik. Bukan solusi akhir.

**Yang perlu dibangun:**
- [ ] Backend: endpoint untuk generate sesi latihan soal terfilter per
      `topic_id` (mis. `GET /topics/{topic}/practice-session` atau serupa)
- [ ] Frontend: halaman/komponen baru untuk mengerjakan latihan soal per
      topik (belum ada route sama sekali -- dicek tidak ada `path="latihan"`
      atau serupa di `App.jsx`)
- [ ] Setelah dibangun, update `practice_link` di
      `PerformanceController::buildTopRecommendations()` untuk mengarah ke
      halaman baru ini, bukan lagi ke `/app/packages`

**Terkait juga (opsional, worth ditinjau bareng):**
- `Packages.jsx` punya filter kategori (`selectedFocusCategoryId`) tapi
  hanya client-side state, tidak bisa di-deep-link lewat URL query param.
  Kalau mau, filter kategori ini bisa dibuat baca dari `?category=` di URL
  supaya paling tidak `practice_link` bisa mengarah ke paket yang sudah
  ter-filter ke section yang relevan, sambil fitur latihan-per-topik yang
  sesungguhnya belum ada.

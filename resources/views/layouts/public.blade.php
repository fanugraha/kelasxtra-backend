<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    {{-- ==================== SEO META TAGS ==================== --}}
    <title>{{ $metaTitle ?? 'Kelasxtra — Try Out, Kelas, dan Latihan Soal SNBT, CPNS, BUMN' }}</title>
    <meta name="description" content="{{ $metaDescription ?? 'Try out, kelas, dan pembahasan soal untuk SNBT, CPNS/SKD, BUMN, dan Ujian Mandiri.' }}">
    <link rel="canonical" href="{{ url()->current() }}">

    {{-- Open Graph / social share --}}
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $metaTitle ?? 'Kelasxtra' }}">
    <meta property="og:description" content="{{ $metaDescription ?? '' }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:site_name" content="Kelasxtra">
    @isset($ogImage)
        <meta property="og:image" content="{{ $ogImage }}">
    @endisset

    <meta name="twitter:card" content="summary_large_image">

    {{-- Biarkan Google index halaman publik ini (default Laravel kadang noindex di beberapa setup, jadi eksplisit di sini) --}}
    <meta name="robots" content="index, follow">

    {{--
        Tailwind via CDN dengan token warna yang SAMA PERSIS dengan React app
        (lihat kelasxtra-frontend/src/styles/theme.css) supaya identitas visual
        konsisten antara halaman publik (Blade) dan dashboard (React).
        Catatan: kalau nanti mau upgrade ke build pipeline Tailwind sendiri
        (lewat Vite yang sudah ada di project ini), cukup ganti bagian ini
        dengan @vite(['resources/css/app.css']) dan pindahkan config di bawah
        ke file CSS/​tailwind.config.
    --}}
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#fdf2f2',
                            100: '#fbe0e0',
                            200: '#f5c2c2',
                            500: '#9a1f1f',
                            600: '#7a1618',
                            700: '#5c1012',
                        },
                        neutral: {
                            100: '#f1f5f9',
                            500: '#64748b',
                        },
                    },
                },
            },
        }
    </script>

    @stack('head')
</head>
<body class="bg-white text-neutral-800 antialiased">

    {{-- Header sederhana, sama seperti versi React --}}
    <header class="sticky top-0 z-40 bg-brand-600 text-white">
        <div class="max-w-6xl mx-auto px-4 md:px-6 h-16 flex items-center justify-between">
            <a href="{{ url('/') }}" class="font-bold text-lg">Kelasxtra</a>
            <nav class="hidden md:flex items-center gap-6 text-sm font-medium">
                <a href="{{ url('/artikel') }}" class="hover:text-brand-100">Artikel</a>
                <a href="{{ url('/#paket') }}" class="hover:text-brand-100">Paket Belajar</a>
                <a href="{{ url('/#faq') }}" class="hover:text-brand-100">FAQ</a>
            </nav>
            <a href="{{ url('/login') }}" class="text-sm font-semibold bg-white/10 hover:bg-white/20 px-4 py-2 rounded-lg transition">
                Masuk
            </a>
        </div>
    </header>

    <main>
        @yield('content')
    </main>

    <footer class="bg-brand-700 text-brand-100 py-10 mt-16">
        <div class="max-w-6xl mx-auto px-4 md:px-6 text-sm flex flex-col md:flex-row justify-between gap-4">
            <p>&copy; {{ date('Y') }} Kelasxtra. Semua hak dilindungi.</p>
            <div class="flex gap-4">
                <a href="{{ url('/artikel') }}" class="hover:text-white">Artikel</a>
                <a href="{{ url('/login') }}" class="hover:text-white">Masuk</a>
            </div>
        </div>
    </footer>
</body>
</html>

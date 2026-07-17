@extends('layouts.public')

@section('content')

    {{-- HERO --}}
    <section class="bg-brand-600 text-white">
        <div class="max-w-6xl mx-auto px-4 md:px-6 py-16 md:py-24 text-center">
            <h1 class="text-3xl md:text-5xl font-bold mb-4 leading-tight">
                Belajar Terarah,<br>Hasil Mengesankan
            </h1>
            <p class="text-brand-100 max-w-xl mx-auto mb-8">
                Try out, kelas, dan pembahasan yang disusun sesuai kategori ujianmu —
                SNBT, CPNS, BUMN, dan Ujian Mandiri.
            </p>

            {{-- Filter kategori: link biasa dengan query string, bukan modal JS.
                 Ini yang bikin tiap kategori jadi URL sendiri & bisa di-index Google. --}}
            <div class="inline-flex flex-wrap justify-center gap-2 bg-white/10 rounded-xl p-2">
                <a href="{{ url('/') }}"
                   class="px-4 py-2 rounded-lg text-sm font-semibold transition {{ !$selectedProgram ? 'bg-white text-brand-600' : 'hover:bg-white/10' }}">
                    Semua Kategori
                </a>
                @foreach ($programs as $program)
                    <a href="{{ url('/?program='.$program->slug) }}"
                       class="px-4 py-2 rounded-lg text-sm font-semibold transition {{ $selectedProgram?->id === $program->id ? 'bg-white text-brand-600' : 'hover:bg-white/10' }}">
                        {{ $program->name }}
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ARTIKEL --}}
    <section id="artikel" class="max-w-6xl mx-auto px-4 md:px-6 py-16">
        <div class="text-center mb-10">
            <h2 class="text-2xl md:text-3xl font-bold text-brand-600 mb-2">Artikel Terupdate</h2>
            <p class="text-neutral-500">Info dan tips seputar SNBT, SKD CPNS, dan BUMN</p>
        </div>

        @if ($articles->isEmpty())
            <p class="text-center text-neutral-500">Belum ada artikel untuk kategori ini.</p>
        @else
            <div class="grid md:grid-cols-3 gap-6">
                @foreach ($articles as $article)
                    <a href="{{ url('/artikel/'.$article->slug) }}"
                       class="block rounded-xl border border-brand-100 overflow-hidden hover:shadow-md transition">
                        <div class="h-40 bg-neutral-100">
                            @if ($article->thumbnail)
                                <img src="{{ $article->thumbnail }}" alt="{{ $article->title }}"
                                     class="w-full h-full object-cover" loading="lazy">
                            @endif
                        </div>
                        <div class="p-4">
                            <h3 class="font-semibold text-brand-700 leading-snug">{{ $article->title }}</h3>
                            @if ($article->excerpt)
                                <p class="text-sm text-neutral-500 mt-2 line-clamp-2">{{ $article->excerpt }}</p>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
        @endif

        <div class="text-center mt-8">
            <a href="{{ url('/artikel') }}"
               class="inline-block bg-brand-600 text-white font-semibold px-6 py-2.5 rounded-lg hover:bg-brand-700 transition">
                Artikel Lainnya
            </a>
        </div>
    </section>

    {{-- PAKET BELAJAR --}}
    <section id="paket" class="bg-brand-50 py-16">
        <div class="max-w-6xl mx-auto px-4 md:px-6">
            <div class="text-center mb-10">
                <h2 class="text-2xl md:text-3xl font-bold text-brand-600 mb-2">Paket Belajar</h2>
                <p class="text-neutral-500">
                    @if ($selectedProgram)
                        Paket belajar untuk kategori {{ $selectedProgram->name }}
                    @else
                        Pilih kategori di atas untuk melihat paket yang paling relevan buat kamu
                    @endif
                </p>
            </div>

            @if ($packages->isEmpty())
                <p class="text-center text-neutral-500">Belum ada paket untuk kategori ini.</p>
            @else
                <div class="grid md:grid-cols-3 gap-6">
                    @foreach ($packages as $pkg)
                        <div class="bg-white rounded-xl border border-brand-100 p-6 flex flex-col">
                            <div class="bg-brand-600 text-white rounded-lg px-4 py-3 text-center font-semibold mb-4">
                                {{ $pkg->name }}
                            </div>
                            <div class="mb-4">
                                <span class="text-2xl font-bold text-brand-600">
                                    Rp{{ number_format($pkg->discount_price ?? $pkg->price, 0, ',', '.') }}
                                </span>
                            </div>
                            <p class="text-sm text-neutral-500 flex-1">{{ $pkg->description }}</p>
                            {{-- Detail & checkout tetap di React (butuh login/state) --}}
                            <a href="{{ url('/login') }}"
                               class="mt-4 bg-brand-600 text-white font-semibold py-2.5 rounded-lg hover:bg-brand-700 transition text-center">
                                Lihat Detail Paket
                            </a>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    {{-- KEUNGGULAN --}}
    <section id="keunggulan" class="max-w-6xl mx-auto px-4 md:px-6 py-16">
        <div class="text-center mb-10">
            <h2 class="text-2xl md:text-3xl font-bold text-brand-600 mb-2">Kenapa Belajar di Kelasxtra</h2>
        </div>
        <div class="grid md:grid-cols-2 gap-6">
            @foreach ([
                ['Soal Sesuai Kisi-Kisi', 'Try out disusun mengikuti pola soal resmi tiap kategori ujian, jadi latihanmu nggak buang waktu.'],
                ['Pembahasan Lengkap', 'Tiap soal dilengkapi pembahasan supaya kamu paham konsepnya, bukan cuma tahu jawabannya.'],
                ['Ranking Nasional', 'Leaderboard membandingkan hasilmu dengan peserta lain sehingga kamu tahu posisimu.'],
                ['Kelas & Materi Terstruktur', 'Jadwal kelas dan materi belajar tersusun rapi, bisa diakses kapan saja dari Beranda kamu.'],
            ] as [$title, $desc])
                <div class="flex gap-4 p-5 rounded-xl bg-brand-50">
                    <div class="w-2 bg-brand-600 rounded-full shrink-0"></div>
                    <div>
                        <h3 class="font-semibold text-brand-700 mb-1">{{ $title }}</h3>
                        <p class="text-sm text-neutral-500">{{ $desc }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- FAQ — pakai <details>/<summary> native, accessible & tanpa JS sama sekali --}}
    <section id="faq" class="bg-brand-50 py-16">
        <div class="max-w-3xl mx-auto px-4 md:px-6">
            <h2 class="text-2xl md:text-3xl font-bold text-brand-600 text-center mb-10">
                Pertanyaan yang Sering Ditanyakan
            </h2>
            <div class="space-y-3">
                @foreach ([
                    ['Apa bedanya Try Out Fulltest dan Persubtes + Bundling?', 'Fulltest berisi soal lengkap semua subtes dalam satu paket sesuai simulasi ujian asli. Persubtes + Bundling memecah latihan per subtes supaya kamu bisa fokus memperkuat bagian yang masih lemah.'],
                    ['Kapan nilai, ranking, dan pembahasan Try Out bisa dilihat?', 'Nilai dan pembahasan langsung muncul begitu kamu menyelesaikan Try Out. Ranking di leaderboard diperbarui otomatis setiap ada peserta baru yang submit.'],
                    ['Apakah materi antar kategori berbeda?', 'Ya. Soal dan pembahasan disesuaikan dengan kisi-kisi resmi masing-masing kategori (SNBT, CPNS/SKD, BUMN, dan Ujian Mandiri).'],
                    ['Masa akses paket belajar sampai kapan?', 'Mengikuti masa aktif yang tertulis di masing-masing paket saat kamu membelinya — bisa dicek lagi di halaman Paket Saya setelah login.'],
                ] as [$q, $a])
                    <details class="bg-white rounded-xl border border-brand-100 group">
                        <summary class="flex items-center justify-between px-5 py-4 text-left font-medium text-brand-700 cursor-pointer list-none">
                            {{ $q }}
                            <span class="text-neutral-500 group-open:hidden">+</span>
                            <span class="text-neutral-500 hidden group-open:inline">−</span>
                        </summary>
                        <p class="px-5 pb-4 text-sm text-neutral-500">{{ $a }}</p>
                    </details>
                @endforeach
            </div>
        </div>
    </section>

@endsection

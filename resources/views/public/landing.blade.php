@extends('layouts.public')

@section('content')

    {{-- HERO --}}
    <section class="relative overflow-hidden bg-brand-600 text-white">
        {{-- Dekorasi radar/blur — murni CSS, tidak butuh JS --}}
        <div class="pointer-events-none absolute top-1/2 left-1/2 w-[900px] h-[900px] -translate-x-1/2 -translate-y-1/2" aria-hidden="true">
            <span class="absolute inset-0 rounded-full border border-white/10"></span>
        </div>
        <div class="pointer-events-none absolute -top-32 -right-20 w-[28rem] h-[28rem] bg-brand-400/20 rounded-full blur-3xl" aria-hidden="true"></div>
        <div class="pointer-events-none absolute -bottom-40 -left-24 w-80 h-80 bg-brand-300/10 rounded-full blur-3xl" aria-hidden="true"></div>

        <div class="relative max-w-3xl mx-auto px-4 md:px-6 pt-20 pb-14 md:pt-28 md:pb-20 text-center">
            <span class="inline-block bg-white/10 border border-white/20 text-brand-50 text-xs font-semibold tracking-wide px-3 py-1.5 rounded-full mb-6">
                Try Out &middot; Kelas &middot; Pembahasan Lengkap
            </span>

            <h1 class="text-4xl sm:text-5xl md:text-6xl font-extrabold mb-5 leading-[1.08] tracking-tight">
                Belajar Terarah,<br>
                <span class="text-amber-300">Hasil Mengesankan</span>
            </h1>

            <p class="text-brand-100 text-base md:text-lg max-w-xl mx-auto mb-9">
                Try out, kelas, dan pembahasan yang disusun sesuai kategori ujianmu —
                SNBT, CPNS, BUMN, dan Ujian Mandiri.
            </p>

            {{-- Filter kategori: link biasa dengan query string, bukan modal JS.
                 Ini yang bikin tiap kategori jadi URL sendiri & bisa di-index Google. --}}
            <div class="flex flex-col sm:flex-row justify-center gap-3 mb-8">
                <a href="{{ url('/#paket') }}"
                   class="bg-amber-400 text-brand-900 font-bold px-7 py-3.5 rounded-xl shadow-lg shadow-black/10 hover:bg-amber-300 transition">
                    Lihat Paket Belajar
                </a>
                <a href="{{ url('/login') }}"
                   class="border border-white/30 font-semibold px-7 py-3.5 rounded-xl hover:bg-white/10 transition">
                    Masuk / Daftar
                </a>
            </div>

            <div class="inline-flex flex-wrap justify-center gap-2 bg-white/10 rounded-xl p-2 mb-14">
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

            {{-- Stats — angka contoh, ganti dengan data asli (mis. dari controller) sebelum tayang --}}
            <div class="grid grid-cols-3 gap-4 max-w-lg mx-auto pt-8 border-t border-white/15">
                <div class="text-center px-2">
                    <div class="tabular-nums text-2xl sm:text-3xl md:text-4xl font-extrabold text-white">12.450+</div>
                    <div class="text-[11px] sm:text-xs md:text-sm text-brand-100 mt-1 tracking-wide">Peserta Terdaftar</div>
                </div>
                <div class="text-center px-2">
                    <div class="tabular-nums text-2xl sm:text-3xl md:text-4xl font-extrabold text-white">87%</div>
                    <div class="text-[11px] sm:text-xs md:text-sm text-brand-100 mt-1 tracking-wide">Lolos Tahap SKD</div>
                </div>
                <div class="text-center px-2">
                    <div class="tabular-nums text-2xl sm:text-3xl md:text-4xl font-extrabold text-white">4.9/5</div>
                    <div class="text-[11px] sm:text-xs md:text-sm text-brand-100 mt-1 tracking-wide">Rating Pengguna</div>
                </div>
            </div>
        </div>
    </section>

    {{-- ARTIKEL --}}
    <section id="artikel" class="max-w-6xl mx-auto px-4 md:px-6 py-16 md:py-24">
        <div class="text-center mb-10">
            <span class="text-xs font-bold tracking-widest text-amber-600 uppercase">Insight</span>
            <h2 class="text-2xl md:text-3xl font-extrabold text-brand-700 mt-2 mb-2">Artikel Terupdate</h2>
            <p class="text-neutral-500">Info dan tips seputar SNBT, SKD CPNS, dan BUMN</p>
        </div>

        @if ($articles->isEmpty())
            <p class="text-center text-neutral-500">Belum ada artikel untuk kategori ini.</p>
        @else
            <div class="grid md:grid-cols-3 gap-6">
                @foreach ($articles as $article)
                    <a href="{{ url('/artikel/'.$article->slug) }}"
                       class="group block h-full rounded-2xl border border-neutral-200 overflow-hidden hover:shadow-xl transition-all duration-300">
                        <div class="h-40 bg-neutral-100 overflow-hidden">
                            @if ($article->thumbnail_url)
                                <img src="{{ $article->thumbnail_url }}" alt="{{ $article->title }}"
                                     class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                                     loading="lazy">
                            @endif
                        </div>
                        <div class="p-5">
                            <h3 class="font-semibold text-brand-700 leading-snug group-hover:text-brand-600 transition-colors">
                                {{ $article->title }}
                            </h3>
                            @if ($article->excerpt)
                                <p class="text-sm text-neutral-500 mt-2 line-clamp-2">{{ $article->excerpt }}</p>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
        @endif

        <div class="text-center mt-10">
            <a href="{{ url('/artikel') }}"
               class="inline-block bg-brand-600 text-white font-semibold px-6 py-2.5 rounded-lg hover:bg-brand-700 transition">
                Artikel Lainnya
            </a>
        </div>
    </section>

    {{-- PAKET BELAJAR --}}
    <section id="paket" class="bg-brand-50 py-16 md:py-24">
        <div class="max-w-6xl mx-auto px-4 md:px-6">
            <div class="text-center mb-12">
                <span class="text-xs font-bold tracking-widest text-amber-600 uppercase">Pilihan Paket</span>
                <h2 class="text-2xl md:text-3xl font-extrabold text-brand-700 mt-2 mb-2">Paket Belajar</h2>
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
                <div class="grid md:grid-cols-3 gap-6 items-start">
                    @foreach ($packages as $index => $pkg)
                        @php $isPopular = $packages->count() > 1 && $index === 1; @endphp
                        <div class="relative bg-white rounded-2xl p-6 flex flex-col h-full transition-all duration-300 hover:shadow-xl {{ $isPopular ? 'border-2 border-amber-400 md:scale-[1.04] shadow-lg' : 'border border-brand-100' }}">
                            @if ($isPopular)
                                <span class="absolute -top-3 left-1/2 -translate-x-1/2 bg-amber-400 text-brand-900 text-xs font-bold px-3 py-1 rounded-full shadow">
                                    Paling Laris
                                </span>
                            @endif
                            <div class="bg-brand-600 text-white rounded-lg px-4 py-3 text-center font-semibold mb-4">
                                {{ $pkg->name }}
                            </div>
                            <div class="mb-4">
                                <span class="text-2xl font-extrabold text-brand-700">
                                    Rp{{ number_format($pkg->discount_price ?? $pkg->price, 0, ',', '.') }}
                                </span>
                            </div>
                            <p class="text-sm text-neutral-500 flex-1">{{ $pkg->description }}</p>
                            {{-- Detail & checkout tetap di React (butuh login/state) --}}
                            <a href="{{ url('/login') }}"
                               class="mt-5 font-semibold py-2.5 rounded-lg transition text-center {{ $isPopular ? 'bg-amber-400 text-brand-900 hover:bg-amber-300' : 'bg-brand-600 text-white hover:bg-brand-700' }}">
                                Lihat Detail Paket
                            </a>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    {{-- KEUNGGULAN --}}
    <section id="keunggulan" class="max-w-6xl mx-auto px-4 md:px-6 py-16 md:py-24">
        <div class="text-center mb-12">
            <span class="text-xs font-bold tracking-widest text-amber-600 uppercase">Mengapa Xtracademy</span>
            <h2 class="text-2xl md:text-3xl font-extrabold text-brand-700 mt-2 mb-2">Kenapa Belajar di Xtracademy</h2>
        </div>
        <div class="grid md:grid-cols-2 gap-6">
            @foreach ([
                ['target', 'Soal Sesuai Kisi-Kisi', 'Try out disusun mengikuti pola soal resmi tiap kategori ujian, jadi latihanmu nggak buang waktu.'],
                ['book', 'Pembahasan Lengkap', 'Tiap soal dilengkapi pembahasan supaya kamu paham konsepnya, bukan cuma tahu jawabannya.'],
                ['trophy', 'Ranking Nasional', 'Leaderboard membandingkan hasilmu dengan peserta lain sehingga kamu tahu posisimu.'],
                ['calendar', 'Kelas & Materi Terstruktur', 'Jadwal kelas dan materi belajar tersusun rapi, bisa diakses kapan saja dari Beranda kamu.'],
            ] as [$icon, $title, $desc])
                <div class="flex gap-4 p-6 rounded-2xl bg-brand-50 hover:bg-brand-100/70 transition-colors h-full">
                    <div class="w-11 h-11 shrink-0 rounded-xl bg-brand-600 text-white flex items-center justify-center">
                        @switch($icon)
                            @case('target')
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="8.5" />
                                    <circle cx="12" cy="12" r="5" />
                                    <circle cx="12" cy="12" r="1.5" fill="currentColor" stroke="none" />
                                </svg>
                                @break
                            @case('book')
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v15H6.5A2.5 2.5 0 0 0 4 20.5V5.5Z" />
                                    <path d="M4 20.5A2.5 2.5 0 0 1 6.5 18H20" />
                                    <path d="m9 10.5 2 2 4-4.5" />
                                </svg>
                                @break
                            @case('trophy')
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M8 4h8v5a4 4 0 0 1-8 0V4Z" />
                                    <path d="M8 5H5a3 3 0 0 0 3 4" />
                                    <path d="M16 5h3a3 3 0 0 1-3 4" />
                                    <path d="M10 15.5h4" />
                                    <path d="M12 13v6.5" />
                                    <path d="M8.5 19.5h7" />
                                </svg>
                                @break
                            @case('calendar')
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3.5" y="5" width="17" height="15.5" rx="2" />
                                    <path d="M3.5 9.5h17" />
                                    <path d="M8 3v4M16 3v4" />
                                    <path d="m8.5 14 2 2 4-4" />
                                </svg>
                                @break
                        @endswitch
                    </div>
                    <div>
                        <h3 class="font-semibold text-brand-700 mb-1">{{ $title }}</h3>
                        <p class="text-sm text-neutral-500">{{ $desc }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- FAQ — pakai <details>/<summary> native, accessible & tanpa JS sama sekali --}}
    <section id="faq" class="bg-brand-50 py-16 md:py-24">
        <div class="max-w-3xl mx-auto px-4 md:px-6">
            <div class="text-center mb-10">
                <span class="text-xs font-bold tracking-widest text-amber-600 uppercase">Bantuan</span>
                <h2 class="text-2xl md:text-3xl font-extrabold text-brand-700 mt-2">
                    Pertanyaan yang Sering Ditanyakan
                </h2>
            </div>
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

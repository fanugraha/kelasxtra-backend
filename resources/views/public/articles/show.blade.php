@extends('layouts.public')

@push('head')
    {{-- Article-specific structured data — bantu Google pahami ini artikel, bukan halaman biasa --}}
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Article",
        "headline": {{ Illuminate\Support\Js::from($article->title) }},
        "datePublished": "{{ $article->published_at?->toIso8601String() }}",
        @if($article->thumbnail)
        "image": {{ Illuminate\Support\Js::from($article->thumbnail) }},
        @endif
        "publisher": {
            "@type": "Organization",
            "name": "Kelasxtra"
        }
    }
    </script>
@endpush

@section('content')
    <div class="max-w-2xl mx-auto px-4 md:px-6 py-12">
        <a href="{{ url('/artikel') }}" class="text-brand-600 hover:underline text-sm mb-6 inline-block">
            ← Kembali ke daftar artikel
        </a>

        @if ($article->thumbnail)
            <img src="{{ $article->thumbnail }}" alt="{{ $article->title }}"
                 class="w-full h-64 object-cover rounded-xl mb-6">
        @endif

        <h1 class="text-2xl md:text-3xl font-bold text-neutral-800 mb-2">{{ $article->title }}</h1>

        @if ($article->published_at)
            <p class="text-sm text-neutral-400 mb-6">
                {{ $article->published_at->translatedFormat('d F Y') }}
                @if ($article->program)
                    &middot; {{ $article->program->name }}
                @endif
            </p>
        @endif

        <div class="prose max-w-none text-neutral-700 whitespace-pre-line">
            {{ $article->content }}
        </div>
    </div>
@endsection

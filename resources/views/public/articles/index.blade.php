@extends('layouts.public')

@section('content')
    <div class="max-w-5xl mx-auto px-4 md:px-6 py-12">
        <a href="{{ url('/') }}" class="text-brand-600 hover:underline text-sm mb-6 inline-block">
            ← Kembali ke Beranda
        </a>
        <h1 class="text-2xl md:text-3xl font-bold text-brand-600 mb-8">Semua Artikel</h1>

        @if ($articles->isEmpty())
            <p class="text-neutral-400">Belum ada artikel.</p>
        @else
            <div class="grid md:grid-cols-3 gap-6">
                @foreach ($articles as $article)
                    <a href="{{ url('/artikel/'.$article->slug) }}"
                       class="block rounded-xl border border-neutral-100 overflow-hidden hover:shadow-md transition">
                        <div class="h-40 bg-neutral-100">
                            @if ($article->thumbnail_url)
                                <img src="{{ $article->thumbnail_url }}" alt="{{ $article->title }}"
                                     class="w-full h-full object-cover" loading="lazy">
                            @endif
                        </div>
                        <div class="p-4">
                            <h3 class="font-semibold text-neutral-800 leading-snug">{{ $article->title }}</h3>
                            @if ($article->excerpt)
                                <p class="text-sm text-neutral-500 mt-2 line-clamp-2">{{ $article->excerpt }}</p>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="mt-10">
                {{ $articles->links() }}
            </div>
        @endif
    </div>
@endsection

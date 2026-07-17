<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Package;
use App\Models\Program;
use Illuminate\Http\Request;

/**
 * Controller untuk halaman publik yang SEO-first (server-rendered Blade).
 *
 * Sengaja TIDAK lewat layer API (Http/Controllers/Api/*) — query langsung ke
 * Eloquent di sini. Alasannya: API layer itu didesain untuk dikonsumsi React
 * (butuh token Sanctum utk beberapa endpoint, format JSON, dsb), sedangkan
 * halaman ini murni server-rendered dan publik, jadi lebih simpel & cepat
 * kalau query langsung ke model, tanpa overhead HTTP call ke diri sendiri.
 */
class PublicController extends Controller
{
    /**
     * GET /  — Landing page.
     * Filter kategori (program) pakai query string ?program=slug, BUKAN
     * client-side state — supaya tiap kategori punya URL sendiri yang bisa
     * di-index Google terpisah (mis. /?program=cpns, /?program=snbt).
     */
    public function landing(Request $request)
    {
        $programs = Program::where('is_active', true)->orderBy('name')->get();

        $selectedProgram = null;
        if ($request->filled('program')) {
            $selectedProgram = $programs->firstWhere('slug', $request->query('program'));
        }

        $packagesQuery = Package::query()->latest();
        if ($selectedProgram) {
            $packagesQuery->where('program_id', $selectedProgram->id);
        }
        $packages = $packagesQuery->limit(6)->get();

        $articlesQuery = Article::published()->orderByDesc('published_at');
        if ($selectedProgram) {
            $articlesQuery->where('program_id', $selectedProgram->id);
        }
        $articles = $articlesQuery->limit(3)->get();

        return view('public.landing', [
            'programs' => $programs,
            'selectedProgram' => $selectedProgram,
            'packages' => $packages,
            'articles' => $articles,
            'metaTitle' => $selectedProgram
                ? "Bimbel {$selectedProgram->name} — Try Out & Kelas Terarah | Kelasxtra"
                : 'Kelasxtra — Try Out, Kelas, dan Latihan Soal SNBT, CPNS, BUMN',
            'metaDescription' => $selectedProgram
                ? "Try out dan kelas persiapan {$selectedProgram->name} dengan pembahasan lengkap dan ranking nasional. Mulai belajar terarah bersama Kelasxtra."
                : 'Try out, kelas, dan pembahasan soal untuk SNBT, CPNS/SKD, BUMN, dan Ujian Mandiri. Belajar terarah, hasil mengesankan bersama Kelasxtra.',
        ]);
    }

    /**
     * GET /artikel — Daftar artikel, dengan pagination server-side asli
     * (bukan client fetch) supaya tiap halaman (?page=2, dst) punya URL
     * yang valid dan bisa di-crawl.
     */
    public function articles(Request $request)
    {
        $articles = Article::published()
            ->orderByDesc('published_at')
            ->paginate(9)
            ->withQueryString();

        return view('public.articles.index', [
            'articles' => $articles,
            'metaTitle' => 'Artikel — Tips & Info SNBT, CPNS, BUMN | Kelasxtra',
            'metaDescription' => 'Kumpulan artikel seputar tips belajar, info SNBT, SKD CPNS, dan BUMN dari Kelasxtra.',
        ]);
    }

    /**
     * GET /artikel/{slug} — Detail artikel.
     */
    public function articleDetail(string $slug)
    {
        $article = Article::published()->where('slug', $slug)->with('program')->firstOrFail();

        return view('public.articles.show', [
            'article' => $article,
            'metaTitle' => $article->title.' | Kelasxtra',
            'metaDescription' => $article->excerpt ?? str($article->content)->limit(160),
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\Http\Request;

class ArticleController extends Controller
{
    public function index(Request $request)
    {
        $query = Article::published()->orderByDesc('published_at');

        if ($request->filled('program_id')) {
            $query->where('program_id', $request->program_id);
        }

        $perPage = $request->integer('per_page', 6);

        return $query->paginate($perPage);
    }

    public function show(string $slug)
    {
        $article = Article::published()->where('slug', $slug)->firstOrFail();

        return $article->load('program');
    }
}

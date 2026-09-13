<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Public\NewsReader;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NewsController extends Controller
{
    public function __construct(private readonly NewsReader $news)
    {
    }

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'kategori' => ['nullable', 'string', 'max:120'],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $category = $filters['kategori'] ?? null;
        $search = $filters['q'] ?? null;

        $news = $this->news->paginate($category, $search, 9);

        return view('public.news.index', [
            'news' => $news,
            'categories' => $this->news->categories(),
            'popular' => $this->news->popular(),
            'tags' => $this->news->tags(),
            'activeCategory' => $category,
            'search' => $search,
            // The magazine hero only makes sense on an unfiltered first page —
            // promoting one story above a filtered result would misrepresent it.
            'lead' => (! $category && ! $search && $news->currentPage() === 1)
                ? $this->news->featured(4)
                : collect(),
        ]);
    }

    public function show(string $slug): View
    {
        // A draft or archived item is a 404 for the public: replying 403 would
        // confirm that the slug exists.
        $news = $this->news->findBySlug($slug);

        abort_if($news === null, 404);

        $this->news->recordView($news);

        return view('public.news.show', [
            'news' => $news,
            'related' => $this->news->related($news),
        ]);
    }
}


<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Public\PageReader;
use Illuminate\View\View;

class PageController extends Controller
{
    public function __invoke(string $slug, PageReader $pages): View
    {
        $page = $pages->findBySlug($slug);

        abort_if($page === null, 404);

        return view('public.pages.show', ['page' => $page]);
    }
}


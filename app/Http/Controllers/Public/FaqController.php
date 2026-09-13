<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Public\FaqReader;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FaqController extends Controller
{
    public function __invoke(Request $request, FaqReader $faqs): View
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:120']]);

        return view('public.faq.index', [
            'groups' => $faqs->grouped($filters['q'] ?? null),
            'search' => $filters['q'] ?? null,
        ]);
    }
}


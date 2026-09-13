<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Public\DocumentReader;
use App\Services\Public\NewsReader;
use App\Services\Public\ServiceReader;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Cross-module search. Rate limited at the route, and the term is validated
 * and length-capped before it ever reaches a LIKE clause.
 */
class SearchController extends Controller
{
    /** The content types a visitor can narrow to. */
    public const TYPES = ['berita', 'layanan', 'dokumen'];

    public function __invoke(
        Request $request,
        NewsReader $news,
        ServiceReader $services,
        DocumentReader $documents,
    ): View {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'min:2', 'max:120'],
            'jenis' => ['nullable', Rule::in(self::TYPES)],
        ], [
            'q.min' => 'Kata kunci minimal 2 karakter.',
        ]);

        $term = $validated['q'] ?? null;
        $type = $validated['jenis'] ?? null;

        // Each module is queried only when the filter allows it, so narrowing
        // actually saves the query rather than just hiding the result.
        $wants = fn (string $name) => $term !== null && ($type === null || $type === $name);

        return view('public.search', [
            'search' => $term,
            'type' => $type,
            'news' => $wants('berita') ? $news->paginate(null, $term, 5) : null,
            'services' => $wants('layanan') ? $services->paginate(null, $term, 5) : null,
            'documents' => $wants('dokumen') ? $documents->paginate(null, $term, 5) : null,
        ]);
    }
}


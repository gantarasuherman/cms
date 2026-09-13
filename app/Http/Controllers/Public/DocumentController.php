<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Public\DocumentReader;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DocumentController extends Controller
{
    public function __construct(private readonly DocumentReader $documents)
    {
    }

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'kategori' => ['nullable', 'string', 'max:120'],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        return view('public.documents.index', [
            'documents' => $this->documents->paginate($filters['kategori'] ?? null, $filters['q'] ?? null),
            'categories' => $this->documents->categories(),
            'activeCategory' => $filters['kategori'] ?? null,
            'search' => $filters['q'] ?? null,
        ]);
    }

    public function show(string $slug): View
    {
        $document = $this->documents->findBySlug($slug);

        abort_if($document === null, 404);

        return view('public.documents.show', ['document' => $document]);
    }
}


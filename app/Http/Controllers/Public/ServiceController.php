<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Public\ServiceReader;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function __construct(private readonly ServiceReader $services)
    {
    }

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'kategori' => ['nullable', 'string', 'max:120'],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        return view('public.services.index', [
            'services' => $this->services->paginate($filters['kategori'] ?? null, $filters['q'] ?? null),
            'categories' => $this->services->categories(),
            'activeCategory' => $filters['kategori'] ?? null,
            'search' => $filters['q'] ?? null,
        ]);
    }

    public function show(string $slug): View
    {
        $service = $this->services->findBySlug($slug);

        abort_if($service === null, 404);

        return view('public.services.show', ['service' => $service]);
    }
}


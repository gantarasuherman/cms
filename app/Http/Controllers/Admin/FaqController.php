<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FaqRequest;
use App\Models\Category;
use App\Models\Faq;
use App\Services\Audit\AuditLogger;
use App\Services\Cache\PublicCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class FaqController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly PublicCache $cache,
    ) {
    }

    public function index(): View
    {
        $this->authorize('viewAny', Faq::class);

        return view('admin.faq.index', [
            'categories' => Category::ofType(Category::TYPE_FAQ)->ordered()->pluck('name', 'id'),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Faq::class);

        $query = Faq::query()->with('category')->select('faqs.*');

        if ($request->filled('category')) {
            $query->where('category_id', $request->integer('category'));
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->string('status')->toString() === 'active');
        }

        return DataTables::eloquent($query)
            ->addColumn('category_name', fn (Faq $faq) => $faq->category?->name ?? '—')
            // Answers hold rich text; the listing shows a plain-text excerpt so
            // markup never reaches the table cell.
            ->addColumn('answer_excerpt', fn (Faq $faq) => Str::limit(strip_tags((string) $faq->answer), 90))
            ->editColumn('is_active', fn (Faq $faq) => view('components.status-badge', [
                'status' => $faq->is_active ? 'active' : 'inactive',
            ])->render())
            ->addColumn('actions', fn (Faq $faq) => view('admin.faq.partials.actions', ['faq' => $faq])->render())
            ->filterColumn('category_name', fn ($q, $keyword) => $q->whereHas('category', fn ($c) => $c->where('name', 'like', "%{$keyword}%")))
            ->rawColumns(['is_active', 'actions'])
            ->toJson();
    }

    public function create(): View
    {
        $this->authorize('create', Faq::class);

        return view('admin.faq.form', $this->formData(new Faq(['is_active' => true])));
    }

    public function store(FaqRequest $request): RedirectResponse
    {
        $this->authorize('create', Faq::class);

        $faq = Faq::create($request->validated());

        $this->audit->recordModel('create', 'faq', $faq);
        $this->cache->forget(PublicCache::FAQS, PublicCache::HOMEPAGE);

        return redirect()->route('admin.faq.index')->with('success', 'FAQ berhasil dibuat.');
    }

    public function edit(Faq $faq): View
    {
        $this->authorize('update', $faq);

        return view('admin.faq.form', $this->formData($faq));
    }

    public function update(FaqRequest $request, Faq $faq): RedirectResponse
    {
        $this->authorize('update', $faq);

        $original = $faq->getOriginal();
        $faq->update($request->validated());

        $this->audit->recordModel('update', 'faq', $faq, $original);
        $this->cache->forget(PublicCache::FAQS, PublicCache::HOMEPAGE);

        return redirect()->route('admin.faq.index')->with('success', 'FAQ berhasil diperbarui.');
    }

    public function destroy(Faq $faq): RedirectResponse
    {
        $this->authorize('delete', $faq);

        $faq->delete();

        $this->audit->record('delete', 'faq', $faq->getKey());
        $this->cache->forget(PublicCache::FAQS, PublicCache::HOMEPAGE);

        return redirect()->route('admin.faq.index')->with('success', 'FAQ berhasil dihapus.');
    }

    /** @return array<string, mixed> */
    private function formData(Faq $faq): array
    {
        return [
            'faq' => $faq,
            'categories' => Category::ofType(Category::TYPE_FAQ)->ordered()->pluck('name', 'id'),
        ];
    }
}


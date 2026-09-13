<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryRequest;
use App\Models\Category;
use App\Services\Audit\AuditLogger;
use App\Services\Cache\PublicCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

/**
 * One controller for every taxonomy. The tree it operates on is fixed per route
 * by a `type` route default (news / service / document / faq), which is also
 * the boundary that stops a news-category URL from reaching a service category.
 */
class CategoryController extends Controller
{
    /** @var array<string, array{label: string, route: string}> */
    private const TYPES = [
        Category::TYPE_NEWS => ['label' => 'Kategori Berita', 'route' => 'admin.news.category'],
        Category::TYPE_SERVICE => ['label' => 'Kategori Layanan', 'route' => 'admin.services.category'],
        Category::TYPE_DOCUMENT => ['label' => 'Kategori Dokumen', 'route' => 'admin.documents.category'],
        Category::TYPE_FAQ => ['label' => 'Kategori FAQ', 'route' => 'admin.faq.category'],
    ];

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly PublicCache $cache,
    ) {
    }

    public function index(): View
    {
        $this->authorize('viewAny', Category::class);
        $type = $this->type();

        return view('admin.categories.index', $this->context($type));
    }

    public function data(): JsonResponse
    {
        $this->authorize('viewAny', Category::class);
        $type = $this->type();

        $query = Category::query()->ofType($type)->with('parent')->select('categories.*');

        return DataTables::eloquent($query)
            ->addColumn('parent_name', fn (Category $category) => $category->parent?->name ?? '—')
            ->editColumn('is_active', fn (Category $category) => view('components.status-badge', [
                'status' => $category->is_active ? 'active' : 'inactive',
            ])->render())
            ->addColumn('actions', fn (Category $category) => view('admin.categories.partials.actions', [
                'category' => $category,
                'routeBase' => self::TYPES[$type]['route'],
            ])->render())
            ->rawColumns(['is_active', 'actions'])
            ->toJson();
    }

    public function create(): View
    {
        $this->authorize('create', Category::class);
        $type = $this->type();

        return view('admin.categories.form', $this->context($type) + [
            'category' => new Category(['type' => $type, 'is_active' => true]),
            'parents' => $this->parentOptions($type, null),
        ]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        $this->authorize('create', Category::class);
        $type = $this->type();

        $category = Category::create($request->validated() + ['type' => $type]);

        $this->audit->recordModel('create', 'category', $category);
        $this->cache->forget(PublicCache::CATEGORIES);

        return redirect()
            ->route(self::TYPES[$type]['route'].'.index')
            ->with('success', 'Kategori berhasil dibuat.');
    }

    public function edit(Category $category): View
    {
        $this->authorize('update', $category);
        $type = $this->type();
        $this->ensureBelongsToType($category, $type);

        return view('admin.categories.form', $this->context($type) + [
            'category' => $category,
            'parents' => $this->parentOptions($type, $category),
        ]);
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        $this->authorize('update', $category);
        $type = $this->type();
        $this->ensureBelongsToType($category, $type);

        $original = $category->getOriginal();
        $category->update($request->validated());

        $this->audit->recordModel('update', 'category', $category, $original);
        $this->cache->forget(PublicCache::CATEGORIES);

        return redirect()
            ->route(self::TYPES[$type]['route'].'.index')
            ->with('success', 'Kategori berhasil diperbarui.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $this->authorize('delete', $category);
        $type = $this->type();
        $this->ensureBelongsToType($category, $type);

        $category->delete();

        $this->audit->record('delete', 'category', $category->getKey());
        $this->cache->forget(PublicCache::CATEGORIES);

        return redirect()
            ->route(self::TYPES[$type]['route'].'.index')
            ->with('success', 'Kategori berhasil dihapus.');
    }

    /**
     * The taxonomy this request belongs to, fixed by the route definition.
     *
     * It is read from the route rather than injected as a method argument:
     * route defaults and bound models are resolved positionally, which made
     * the two silently swap places.
     */
    private function type(): string
    {
        $type = (string) request()->route()->defaults['type'];

        abort_unless(isset(self::TYPES[$type]), 404);

        return $type;
    }

    /**
     * Guards against reaching a category of another taxonomy by editing the id
     * in the URL (IDOR): /admin/news/category/{id} may only touch news rows.
     */
    private function ensureBelongsToType(Category $category, string $type): void
    {
        abort_unless($category->type === $type, 404);
    }

    /** @return array<string, mixed> */
    private function context(string $type): array
    {
        abort_unless(isset(self::TYPES[$type]), 404);

        return [
            'type' => $type,
            'heading' => self::TYPES[$type]['label'],
            'routeBase' => self::TYPES[$type]['route'],
        ];
    }

    /**
     * Parent choices, excluding the category being edited so a cycle cannot be
     * created through the form.
     *
     * @return array<int, string>
     */
    private function parentOptions(string $type, ?Category $category): array
    {
        return Category::ofType($type)
            ->when($category?->exists, fn ($query) => $query->whereKeyNot($category->getKey()))
            ->ordered()
            ->pluck('name', 'id')
            ->all();
    }
}


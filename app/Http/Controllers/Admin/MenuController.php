<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MenuRequest;
use App\Models\AdminMenu;
use App\Models\Menu;
use App\Models\PublicMenu;
use App\Services\Audit\AuditLogger;
use App\Services\Menu\MenuService;
use App\Support\Permissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * One controller for both navigation trees. Which tree is being edited comes
 * from the route definition, the same way the taxonomy screens work.
 */
class MenuController extends Controller
{
    public function __construct(
        private readonly MenuService $menus,
        private readonly AuditLogger $audit,
    ) {
    }

    public function index(): View
    {
        $this->authorize('viewAny', $this->model());

        $model = $this->model();

        return view('admin.menus.index', [
            'tree' => $model::query()->roots()->with('childrenRecursive')->orderBy('sort_order')->get(),
            'kind' => $this->kind(),
            'routeBase' => $this->routeBase(),
            'heading' => $this->heading(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', $this->model());

        $model = $this->model();

        return view('admin.menus.form', $this->formData(new $model(['is_active' => true, 'target' => '_self'])));
    }

    public function store(MenuRequest $request): RedirectResponse
    {
        $this->authorize('create', $this->model());

        $model = $this->model();
        $menu = $model::create($request->validated());

        $this->audit->recordModel('create', $this->kind().'_menu', $menu);
        $this->menus->forget();

        return redirect()->route($this->routeBase().'.index')->with('success', 'Menu berhasil ditambahkan.');
    }

    public function edit(Menu $menu): View
    {
        $this->authorize('update', $menu);

        return view('admin.menus.form', $this->formData($menu));
    }

    public function update(MenuRequest $request, Menu $menu): RedirectResponse
    {
        $this->authorize('update', $menu);

        $original = $menu->getOriginal();
        $menu->update($request->validated());

        $this->audit->recordModel('update', $this->kind().'_menu', $menu, $original);
        $this->menus->forget();

        return redirect()->route($this->routeBase().'.index')->with('success', 'Menu berhasil diperbarui.');
    }

    public function destroy(Menu $menu): RedirectResponse
    {
        $this->authorize('delete', $menu);

        // Children cascade at the database level; the tree never keeps orphans.
        $menu->delete();

        $this->audit->record('delete', $this->kind().'_menu', $menu->getKey());
        $this->menus->forget();

        return redirect()->route($this->routeBase().'.index')->with('success', 'Menu berhasil dihapus.');
    }

    /** Persists a new order and nesting in one atomic request. */
    public function reorder(Request $request): JsonResponse
    {
        $this->authorize('create', $this->model());

        $model = $this->model();
        $table = (new $model())->getTable();

        $validated = $request->validate([
            'order' => ['required', 'array'],
            'order.*.id' => ['required', 'integer', 'exists:'.$table.',id'],
            'order.*.parent_id' => ['nullable', 'integer', 'exists:'.$table.',id'],
        ]);

        DB::transaction(function () use ($validated, $model) {
            foreach ($validated['order'] as $position => $entry) {
                $model::whereKey($entry['id'])->update([
                    'parent_id' => $entry['parent_id'] ?? null,
                    'sort_order' => ($position + 1) * 10,
                ]);
            }
        });

        $this->audit->record('reorder', $this->kind().'_menu', null, null, $validated);
        $this->menus->forget();

        return response()->json(['success' => true, 'message' => 'Urutan menu disimpan.']);
    }

    /** @return array<string, mixed> */
    private function formData(Menu $menu): array
    {
        $model = $this->model();

        return [
            'menu' => $menu,
            'kind' => $this->kind(),
            'routeBase' => $this->routeBase(),
            'heading' => $this->heading(),
            'parents' => $model::query()
                ->when($menu->exists, fn ($query) => $query->whereKeyNot($menu->getKey()))
                ->orderBy('sort_order')
                ->pluck('title', 'id')
                ->all(),
            'permissions' => collect(Permissions::all())->mapWithKeys(fn (string $p) => [$p => $p])->all(),
        ];
    }

    /** @return class-string<Menu> */
    private function model(): string
    {
        return request()->route()->defaults['model'];
    }

    private function kind(): string
    {
        return $this->model() === AdminMenu::class ? 'admin' : 'public';
    }

    private function routeBase(): string
    {
        return 'admin.menus.'.$this->kind();
    }

    private function heading(): string
    {
        return $this->model() === AdminMenu::class ? 'Menu Admin' : 'Menu Publik';
    }
}


<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\HomepageSection;
use App\Services\Audit\AuditLogger;
use App\Services\Cache\PublicCache;
use App\Support\HomepageSections;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class HomepageController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly PublicCache $cache,
    ) {
    }

    public function index(): View
    {
        $this->authorize('viewAny', HomepageSection::class);

        return view('admin.settings.homepage.index', [
            'sections' => HomepageSection::orderBy('sort_order')->get(),
            'types' => HomepageSections::types(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', HomepageSection::class);

        return view('admin.settings.homepage.form', [
            'section' => new HomepageSection(['is_active' => true]),
            'types' => HomepageSections::types(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', HomepageSection::class);

        $data = $this->validated($request);
        $data['sort_order'] = (int) (HomepageSection::max('sort_order') + 10);

        $section = HomepageSection::create($data);

        $this->audit->recordModel('create', 'homepage_section', $section);
        $this->cache->forget(PublicCache::HOMEPAGE);

        return redirect()->route('admin.settings.homepage.index')->with('success', 'Bagian beranda berhasil ditambahkan.');
    }

    public function edit(HomepageSection $homepage): View
    {
        $this->authorize('update', $homepage);

        return view('admin.settings.homepage.form', [
            'section' => $homepage,
            'types' => HomepageSections::types(),
        ]);
    }

    public function update(Request $request, HomepageSection $homepage): RedirectResponse
    {
        $this->authorize('update', $homepage);

        $original = $homepage->getOriginal();
        $homepage->update($this->validated($request));

        $this->audit->recordModel('update', 'homepage_section', $homepage, $original);
        $this->cache->forget(PublicCache::HOMEPAGE);

        return redirect()->route('admin.settings.homepage.index')->with('success', 'Bagian beranda berhasil diperbarui.');
    }

    public function destroy(HomepageSection $homepage): RedirectResponse
    {
        $this->authorize('delete', $homepage);

        $homepage->delete();

        $this->audit->record('delete', 'homepage_section', $homepage->getKey());
        $this->cache->forget(PublicCache::HOMEPAGE);

        return back()->with('success', 'Bagian beranda berhasil dihapus.');
    }

    /**
     * Persists a new order. Accepts the ids in their intended sequence rather
     * than per-row positions, so a reorder is one atomic request whether it
     * came from dragging or from the keyboard buttons.
     */
    public function reorder(Request $request): JsonResponse
    {
        $this->authorize('update', new HomepageSection());

        $validated = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer', 'exists:homepage_sections,id'],
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['order'] as $position => $id) {
                HomepageSection::whereKey($id)->update(['sort_order' => ($position + 1) * 10]);
            }
        });

        $this->audit->record('reorder', 'homepage_section', null, null, ['order' => $validated['order']]);
        $this->cache->forget(PublicCache::HOMEPAGE);

        return response()->json(['success' => true, 'message' => 'Urutan bagian beranda disimpan.']);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'type' => ['required', 'string', \Illuminate\Validation\Rule::in(array_keys(HomepageSections::types()))],
            'title' => ['nullable', 'string', 'max:150'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:24'],
        ]);

        // Per-type options live in the JSON column so a new section type does
        // not need a migration.
        $data['settings'] = array_filter(['limit' => $data['limit'] ?? null], fn ($v) => $v !== null);
        unset($data['limit']);

        return $data;
    }
}


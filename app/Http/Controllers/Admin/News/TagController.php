<?php

namespace App\Http\Controllers\Admin\News;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use App\Services\Audit\AuditLogger;
use App\Services\Cache\PublicCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class TagController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly PublicCache $cache,
    ) {
    }

    public function index(): View
    {
        $this->authorize('viewAny', Tag::class);

        return view('admin.news.tags.index', ['tag' => new Tag()]);
    }

    public function data(): JsonResponse
    {
        $this->authorize('viewAny', Tag::class);

        $query = Tag::query()->withCount('news')->select('tags.*');

        return DataTables::eloquent($query)
            ->addColumn('news_total', fn (Tag $tag) => (string) $tag->news_count)
            ->addColumn('actions', fn (Tag $tag) => view('admin.news.tags.partials.actions', ['tag' => $tag])->render())
            ->rawColumns(['actions'])
            ->toJson();
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Tag::class);

        $tag = Tag::create($this->validated($request));

        $this->audit->recordModel('create', 'tag', $tag);
        $this->cache->forget(PublicCache::NEWS);

        return back()->with('success', 'Tag berhasil ditambahkan.');
    }

    public function edit(Tag $tag): View
    {
        $this->authorize('update', $tag);

        return view('admin.news.tags.edit', ['tag' => $tag]);
    }

    public function update(Request $request, Tag $tag): RedirectResponse
    {
        $this->authorize('update', $tag);

        $original = $tag->getOriginal();
        $tag->update($this->validated($request, $tag));

        $this->audit->recordModel('update', 'tag', $tag, $original);
        $this->cache->forget(PublicCache::NEWS);

        return redirect()->route('admin.news.tag.index')->with('success', 'Tag berhasil diperbarui.');
    }

    public function destroy(Tag $tag): RedirectResponse
    {
        $this->authorize('delete', $tag);

        // The pivot rows go with it; the articles themselves are untouched.
        $tag->news()->detach();
        $tag->delete();

        $this->audit->record('delete', 'tag', $tag->getKey());
        $this->cache->forget(PublicCache::NEWS);

        return back()->with('success', 'Tag berhasil dihapus.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Tag $tag = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'slug' => [
                'nullable', 'string', 'max:60', 'alpha_dash',
                Rule::unique('tags', 'slug')->ignore($tag?->getKey()),
            ],
        ], [], ['name' => 'nama tag']);
    }
}


<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Services\Audit\AuditLogger;
use App\Services\Cache\PublicCache;
use App\Services\Media\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class PageController extends Controller
{
    private const IMAGE_DIRECTORY = 'pages';

    public function __construct(
        private readonly MediaService $media,
        private readonly AuditLogger $audit,
        private readonly PublicCache $cache,
    ) {
    }

    public function index(): View
    {
        $this->authorize('viewAny', Page::class);

        return view('admin.pages.index', ['statuses' => $this->statusOptions()]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Page::class);

        $query = Page::query()->select('pages.*');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return DataTables::eloquent($query)
            ->editColumn('status', fn (Page $page) => view('components.status-badge', ['status' => $page->status])->render())
            ->editColumn('published_at', fn (Page $page) => $page->published_at?->translatedFormat('d M Y') ?? '—')
            ->addColumn('actions', fn (Page $page) => view('admin.pages.partials.actions', ['page' => $page])->render())
            ->rawColumns(['status', 'actions'])
            ->toJson();
    }

    public function create(): View
    {
        $this->authorize('create', Page::class);

        return view('admin.pages.form', [
            'page' => new Page(['status' => Page::STATUS_DRAFT]),
            'statuses' => $this->statusOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Page::class);

        $data = $this->validated($request);
        $page = new Page($this->attributes($data));

        if ($request->hasFile('featured_image')) {
            $page->featured_image = $this->media->storePublic($request->file('featured_image'), self::IMAGE_DIRECTORY);
        }

        $page->save();

        $this->audit->recordModel('create', 'page', $page);
        $this->cache->forget(PublicCache::HOMEPAGE);

        return redirect()->route('admin.pages.edit', $page)->with('success', 'Halaman berhasil dibuat.');
    }

    public function edit(Page $page): View
    {
        $this->authorize('update', $page);

        return view('admin.pages.form', ['page' => $page, 'statuses' => $this->statusOptions()]);
    }

    public function update(Request $request, Page $page): RedirectResponse
    {
        $this->authorize('update', $page);

        $data = $this->validated($request, $page);
        $original = $page->getOriginal();

        $page->fill($this->attributes($data));

        if ($request->hasFile('featured_image')) {
            $page->featured_image = $this->media->replacePublic($page->featured_image, $request->file('featured_image'), self::IMAGE_DIRECTORY);
        } elseif ($request->boolean('remove_featured_image')) {
            $this->media->delete($page->featured_image);
            $page->featured_image = null;
        }

        $page->save();

        $this->audit->recordModel('update', 'page', $page, $original);
        $this->cache->forget(PublicCache::HOMEPAGE);

        return redirect()->route('admin.pages.edit', $page)->with('success', 'Halaman berhasil diperbarui.');
    }

    public function destroy(Page $page): RedirectResponse
    {
        $this->authorize('delete', $page);

        $page->delete();

        $this->audit->record('delete', 'page', $page->getKey());
        $this->cache->forget(PublicCache::HOMEPAGE);

        return redirect()->route('admin.pages.index')->with('success', 'Halaman berhasil dihapus.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Page $page = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable', 'string', 'max:255', 'alpha_dash',
                Rule::unique('pages', 'slug')->ignore($page?->getKey())->withoutTrashed(),
            ],
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'content' => ['nullable', 'string'],
            'featured_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_featured_image' => ['nullable', 'boolean'],
            'status' => ['required', Rule::in(Page::statuses())],
            'published_at' => ['nullable', 'date'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:500'],
            'seo_keywords' => ['nullable', 'string', 'max:255'],
        ], [], ['title' => 'judul', 'content' => 'isi halaman']);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        $attributes = collect($data)
            ->only(['title', 'slug', 'excerpt', 'content', 'status', 'published_at',
                'seo_title', 'seo_description', 'seo_keywords'])
            ->all();

        if (($attributes['status'] ?? null) === Page::STATUS_PUBLISHED && blank($attributes['published_at'] ?? null)) {
            $attributes['published_at'] = now();
        }

        return $attributes;
    }

    /** @return array<string, string> */
    private function statusOptions(): array
    {
        return [
            Page::STATUS_DRAFT => 'Draf',
            Page::STATUS_PUBLISHED => 'Terbit',
            Page::STATUS_ARCHIVED => 'Arsip',
        ];
    }
}


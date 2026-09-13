<?php

namespace App\Http\Controllers\Admin\News;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\NewsRequest;
use App\Models\Category;
use App\Models\News;
use App\Services\News\NewsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class NewsController extends Controller
{
    public function __construct(private readonly NewsService $service)
    {
    }

    public function index(): View
    {
        $this->authorize('viewAny', News::class);

        return view('admin.news.index', [
            'categories' => Category::ofType(Category::TYPE_NEWS)->ordered()->pluck('name', 'id'),
            'statuses' => $this->statusOptions(),
        ]);
    }

    /**
     * Server-side data source for the listing. All searching, sorting and
     * paging happens in SQL via Yajra; the browser only renders the page it
     * asked for.
     */
    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', News::class);

        $query = News::query()->with(['author', 'categories'])->select('news.*');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('category')) {
            $query->whereHas('categories', fn ($q) => $q->where('categories.id', $request->integer('category')));
        }

        return DataTables::eloquent($query)
            ->addColumn('categories_list', fn (News $news) => $news->categories->pluck('name')->join(', ') ?: '—')
            ->addColumn('author_name', fn (News $news) => $news->author?->name ?? '—')
            ->editColumn('status', fn (News $news) => view('components.status-badge', ['status' => $news->status])->render())
            ->editColumn('published_at', fn (News $news) => $news->published_at?->translatedFormat('d M Y H:i') ?? '—')
            ->addColumn('actions', fn (News $news) => view('admin.news.partials.actions', ['news' => $news])->render())
            ->filterColumn('author_name', fn ($query, $keyword) => $query->whereHas('author', fn ($q) => $q->where('name', 'like', "%{$keyword}%")))
            ->orderColumn('author_name', fn ($query, $direction) => $query->orderBy(
                \App\Models\User::select('name')->whereColumn('users.id', 'news.author_id'), $direction
            ))
            // Only these two columns carry markup, and both are rendered from
            // escaped Blade views rather than concatenated strings.
            ->rawColumns(['status', 'actions'])
            ->toJson();
    }

    public function create(): View
    {
        $this->authorize('create', News::class);

        return view('admin.news.form', $this->formData(new News(['status' => News::STATUS_DRAFT])));
    }

    public function store(NewsRequest $request): RedirectResponse
    {
        $this->authorize('create', News::class);

        $news = $this->service->create($request->validated(), $request->file('featured_image'));

        return redirect()
            ->route('admin.news.edit', $news)
            ->with('success', 'Berita berhasil dibuat.');
    }

    public function show(News $news): RedirectResponse
    {
        $this->authorize('view', $news);

        return redirect()->route('admin.news.edit', $news);
    }

    public function edit(News $news): View
    {
        $this->authorize('update', $news);

        return view('admin.news.form', $this->formData($news->load(['categories', 'tags'])));
    }

    public function update(NewsRequest $request, News $news): RedirectResponse
    {
        $this->authorize('update', $news);

        $this->service->update($news, $request->validated(), $request->file('featured_image'));

        return redirect()
            ->route('admin.news.edit', $news)
            ->with('success', 'Berita berhasil diperbarui.');
    }

    public function destroy(News $news): RedirectResponse
    {
        $this->authorize('delete', $news);

        $this->service->delete($news);

        return redirect()
            ->route('admin.news.index')
            ->with('success', 'Berita berhasil dihapus.');
    }

    /** Publish / archive / return-to-draft, guarded by the publish ability. */
    public function changeStatus(Request $request, News $news): RedirectResponse
    {
        $this->authorize('publish', $news);

        $validated = $request->validate([
            'status' => ['required', \Illuminate\Validation\Rule::in(News::statuses())],
        ]);

        $this->service->changeStatus($news, $validated['status']);

        return back()->with('success', 'Status berita diperbarui.');
    }

    /** @return array<string, mixed> */
    private function formData(News $news): array
    {
        return [
            'news' => $news,
            'categories' => Category::ofType(Category::TYPE_NEWS)->ordered()->pluck('name', 'id'),
            'selectedCategories' => $news->exists ? $news->categories->pluck('id')->all() : [],
            'statuses' => $this->statusOptions(),
        ];
    }

    /** @return array<string, string> */
    private function statusOptions(): array
    {
        return [
            News::STATUS_DRAFT => 'Draf',
            News::STATUS_PUBLISHED => 'Terbit',
            News::STATUS_ARCHIVED => 'Arsip',
        ];
    }
}


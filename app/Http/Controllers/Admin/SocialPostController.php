<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SocialPostRequest;
use App\Models\SocialPost;
use App\Services\Audit\AuditLogger;
use App\Services\Cache\PublicCache;
use App\Services\Media\MediaService;
use App\Services\Social\SocialSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class SocialPostController extends Controller
{
    private const IMAGE_DIRECTORY = 'social-posts';

    public function __construct(
        private readonly MediaService $media,
        private readonly AuditLogger $audit,
        private readonly PublicCache $cache,
    ) {
    }

    public function index(): View
    {
        $this->authorize('viewAny', SocialPost::class);

        return view('admin.social-posts.index', [
            'posts' => SocialPost::orderBy('sort_order')->orderBy('id')->get(),
            'providers' => app(SocialSyncService::class)->providers(),
            'syncEnabled' => (bool) config('social.sync_enabled'),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SocialPost::class);

        $query = SocialPost::query()->select('social_posts.*');

        if ($request->filled('status')) {
            $query->where('is_active', $request->string('status')->toString() === 'active');
        }

        if ($request->filled('platform')) {
            $query->where('platform', $request->string('platform')->toString());
        }

        return DataTables::eloquent($query)
            ->addColumn('preview', fn (SocialPost $post) => view('admin.social-posts.partials.preview', ['post' => $post])->render())
            ->editColumn('platform', fn (SocialPost $post) => e($post->platformLabel()))
            ->addColumn('account', fn (SocialPost $post) => '@'.e($post->handle()))
            ->addColumn('engagement', fn (SocialPost $post) => view('admin.social-posts.partials.engagement', ['post' => $post])->render())
            ->addColumn('sync', fn (SocialPost $post) => view('admin.social-posts.partials.sync', ['post' => $post])->render())
            ->addColumn('caption', fn (SocialPost $post) => e($post->excerpt(80)) ?: '—')
            ->addColumn('status', fn (SocialPost $post) => view('admin.social-posts.partials.status', ['post' => $post])->render())
            ->editColumn('posted_at', fn (SocialPost $post) => $post->posted_at?->translatedFormat('d M Y') ?? '—')
            ->addColumn('actions', fn (SocialPost $post) => view('admin.social-posts.partials.actions', ['post' => $post])->render())
            ->rawColumns(['preview', 'engagement', 'sync', 'status', 'actions'])
            ->toJson();
    }

    public function create(): View
    {
        $this->authorize('create', SocialPost::class);

        return view('admin.social-posts.form', ['post' => new SocialPost(['is_active' => true, 'platform' => 'instagram'])]);
    }

    public function store(SocialPostRequest $request): RedirectResponse
    {
        $this->authorize('create', SocialPost::class);

        $post = new SocialPost($request->safe()->except('image'));

        if ($request->hasFile('image')) {
            $post->image = $this->media->storePublic($request->file('image'), self::IMAGE_DIRECTORY);
        }

        $post->is_active = $request->boolean('is_active');
        $post->sync_enabled = $request->boolean('sync_enabled');
        $post->sort_order = (int) (SocialPost::max('sort_order') + 10);
        $post->save();

        $this->audit->recordModel('create', 'social_post', $post);
        $this->cache->forget(PublicCache::SOCIAL_POSTS, PublicCache::HOMEPAGE);

        return redirect()->route('admin.social-posts.index')->with('success', 'Unggahan berhasil ditambahkan.');
    }

    public function edit(SocialPost $socialPost): View
    {
        $this->authorize('update', $socialPost);

        return view('admin.social-posts.form', ['post' => $socialPost]);
    }

    public function update(SocialPostRequest $request, SocialPost $socialPost): RedirectResponse
    {
        $this->authorize('update', $socialPost);

        $original = $socialPost->getOriginal();
        $socialPost->fill($request->safe()->except('image'));
        $socialPost->is_active = $request->boolean('is_active');
        $socialPost->sync_enabled = $request->boolean('sync_enabled');

        if ($request->hasFile('image')) {
            // An upload stands until the next sync, which follows the platform
            // for the picture exactly as it does for the caption. An editor who
            // wants their own picture to stand turns sync off for this post —
            // one rule for every field, rather than a per-field exception.
            $socialPost->image = $this->media->replacePublic($socialPost->image, $request->file('image'), self::IMAGE_DIRECTORY);
        }

        $socialPost->save();

        $this->audit->recordModel('update', 'social_post', $socialPost, $original);
        $this->cache->forget(PublicCache::SOCIAL_POSTS, PublicCache::HOMEPAGE);

        return redirect()->route('admin.social-posts.index')->with('success', 'Unggahan berhasil diperbarui.');
    }

    public function destroy(SocialPost $socialPost): RedirectResponse
    {
        $this->authorize('delete', $socialPost);

        // Slides first: the rows go with the post through the foreign key,
        // but their files on disk would be left behind.
        foreach ($socialPost->media as $slide) {
            $this->media->delete($slide->path);
        }

        $this->media->delete($socialPost->image);
        $socialPost->delete();

        $this->audit->record('delete', 'social_post', $socialPost->getKey());
        $this->cache->forget(PublicCache::SOCIAL_POSTS, PublicCache::HOMEPAGE);

        return redirect()->route('admin.social-posts.index')->with('success', 'Unggahan berhasil dihapus.');
    }

    public function toggle(SocialPost $socialPost): RedirectResponse
    {
        $this->authorize('update', $socialPost);

        $socialPost->update(['is_active' => ! $socialPost->is_active]);

        $this->audit->record($socialPost->is_active ? 'activate' : 'deactivate', 'social_post', $socialPost->getKey());
        $this->cache->forget(PublicCache::SOCIAL_POSTS, PublicCache::HOMEPAGE);

        return back()->with('success', $socialPost->is_active ? 'Unggahan ditampilkan.' : 'Unggahan disembunyikan.');
    }

    /**
     * Creates a post from nothing but its link.
     *
     * The platform is worked out from the URL, the record is written, and the
     * sync fills in the picture, the account name, the caption, the date and
     * the figures. Whatever it could not fetch stays editable on the form.
     */
    public function fetch(Request $request, SocialSyncService $sync): RedirectResponse
    {
        $this->authorize('create', SocialPost::class);

        $validated = $request->validate([
            'permalink' => ['required', 'string', 'max:255', 'regex:/^https?:\/\//i'],
        ], [
            'permalink.required' => 'Tempelkan tautan unggahannya.',
            'permalink.regex' => 'Tautan harus diawali http:// atau https://.',
        ]);

        $permalink = $validated['permalink'];
        $platform = SocialPost::platformFromUrl($permalink);

        if ($platform === null) {
            return back()
                ->withInput()
                ->withErrors(['permalink' => 'Platform tidak dikenali dari tautan itu. Yang didukung: '.implode(', ', SocialPost::PLATFORMS).'.']);
        }

        if ($existing = SocialPost::where('permalink', $permalink)->first()) {
            return redirect()
                ->route('admin.social-posts.edit', $existing)
                ->with('warning', 'Unggahan itu sudah ada. Ini datanya.');
        }

        $post = new SocialPost([
            'platform' => $platform,
            'permalink' => $permalink,
            'is_active' => true,
        ]);
        $post->sort_order = (int) (SocialPost::max('sort_order') + 10);
        $post->save();

        $result = $sync->sync($post);

        $this->audit->recordModel('create', 'social_post', $post);
        $this->cache->forget(PublicCache::SOCIAL_POSTS, PublicCache::HOMEPAGE);

        return redirect()
            ->route('admin.social-posts.edit', $post)
            ->with(
                $result->successful() ? 'success' : 'warning',
                $result->successful()
                    ? 'Data diambil dari '.$post->platformLabel().'. Periksa lalu simpan.'
                    : 'Unggahan dibuat, tetapi datanya belum bisa diambil: '.$result->message,
            );
    }

    /** Reads one post's figures back from its platform, on demand. */
    public function sync(SocialPost $socialPost, SocialSyncService $sync): RedirectResponse
    {
        $this->authorize('update', $socialPost);

        $result = $sync->sync($socialPost);

        $this->audit->record('sync', 'social_post', $socialPost->getKey(), null, ['status' => $result->status]);

        return back()->with(
            $result->successful() ? 'success' : 'warning',
            $result->successful() ? 'Angka diperbarui dari '.$socialPost->platformLabel().'.' : $result->message,
        );
    }

    public function syncAll(SocialSyncService $sync): RedirectResponse
    {
        $this->authorize('update', new SocialPost());

        $tally = $sync->syncMany(SocialPost::syncable()->get());

        $this->audit->record('sync', 'social_post', null, null, $tally);

        return back()->with(
            $tally['ok'] > 0 ? 'success' : 'warning',
            sprintf('%d tersinkron, %d gagal, %d tidak didukung.', $tally['ok'], $tally['failed'], $tally['unsupported']),
        );
    }

    public function reorder(Request $request): JsonResponse
    {
        $this->authorize('update', new SocialPost());

        $validated = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer', 'exists:social_posts,id'],
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['order'] as $position => $id) {
                SocialPost::whereKey($id)->update(['sort_order' => ($position + 1) * 10]);
            }
        });

        $this->audit->record('reorder', 'social_post', null, null, ['order' => $validated['order']]);
        $this->cache->forget(PublicCache::SOCIAL_POSTS, PublicCache::HOMEPAGE);

        return response()->json(['success' => true, 'message' => 'Urutan unggahan disimpan.']);
    }
}

<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CarouselSlideRequest;
use App\Models\CarouselSlide;
use App\Services\Audit\AuditLogger;
use App\Services\Cache\PublicCache;
use App\Services\Media\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class CarouselController extends Controller
{
    private const IMAGE_DIRECTORY = 'carousel';

    public function __construct(
        private readonly MediaService $media,
        private readonly AuditLogger $audit,
        private readonly PublicCache $cache,
    ) {
    }

    public function index(): View
    {
        $this->authorize('viewAny', CarouselSlide::class);

        return view('admin.settings.carousel.index');
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CarouselSlide::class);

        $query = CarouselSlide::query()->select('carousel_slides.*');

        if ($request->filled('status')) {
            $query->where('is_active', $request->string('status')->toString() === 'active');
        }

        return DataTables::eloquent($query)
            ->addColumn('preview', fn (CarouselSlide $slide) => view('admin.settings.carousel.partials.preview', ['slide' => $slide])->render())
            ->editColumn('title', fn (CarouselSlide $slide) => $slide->title ?: '(tanpa judul)')
            ->editColumn('category', fn (CarouselSlide $slide) => $slide->category ?: '—')
            // The flag alone would say "active" for a slide whose window has
            // closed, so the badge reports what the public actually sees.
            ->addColumn('status', fn (CarouselSlide $slide) => view('admin.settings.carousel.partials.status', ['slide' => $slide])->render())
            ->editColumn('start_date', fn (CarouselSlide $slide) => $slide->start_date?->translatedFormat('d M Y') ?? 'Kapan saja')
            ->editColumn('end_date', fn (CarouselSlide $slide) => $slide->end_date?->translatedFormat('d M Y') ?? 'Seterusnya')
            ->addColumn('actions', fn (CarouselSlide $slide) => view('admin.settings.carousel.partials.actions', ['slide' => $slide])->render())
            ->rawColumns(['preview', 'status', 'actions'])
            ->toJson();
    }

    public function create(): View
    {
        $this->authorize('create', CarouselSlide::class);

        return view('admin.settings.carousel.form', [
            'slide' => new CarouselSlide(['is_active' => true, 'button_text' => 'Selengkapnya']),
        ]);
    }

    public function store(CarouselSlideRequest $request): RedirectResponse
    {
        $this->authorize('create', CarouselSlide::class);

        $slide = new CarouselSlide($request->safe()->except('image'));
        $slide->image = $this->media->storePublic($request->file('image'), self::IMAGE_DIRECTORY);
        $slide->sort_order = $request->filled('sort_order')
            ? (int) $request->input('sort_order')
            : (int) (CarouselSlide::max('sort_order') + 10);
        $slide->save();

        $this->audit->recordModel('create', 'carousel', $slide);
        $this->cache->forget(PublicCache::CAROUSEL, PublicCache::HOMEPAGE);

        return redirect()->route('admin.settings.carousel.index')->with('success', 'Slide berhasil ditambahkan.');
    }

    public function edit(CarouselSlide $carousel): View
    {
        $this->authorize('update', $carousel);

        return view('admin.settings.carousel.form', ['slide' => $carousel]);
    }

    public function update(CarouselSlideRequest $request, CarouselSlide $carousel): RedirectResponse
    {
        $this->authorize('update', $carousel);

        $original = $carousel->getOriginal();
        $carousel->fill($request->safe()->except('image'));

        if ($request->hasFile('image')) {
            $carousel->image = $this->media->replacePublic($carousel->image, $request->file('image'), self::IMAGE_DIRECTORY);
        }

        $carousel->save();

        $this->audit->recordModel('update', 'carousel', $carousel, $original);
        $this->cache->forget(PublicCache::CAROUSEL, PublicCache::HOMEPAGE);

        return redirect()->route('admin.settings.carousel.index')->with('success', 'Slide berhasil diperbarui.');
    }

    public function destroy(CarouselSlide $carousel): RedirectResponse
    {
        $this->authorize('delete', $carousel);

        $this->media->delete($carousel->image);
        $carousel->delete();

        $this->audit->record('delete', 'carousel', $carousel->getKey());
        $this->cache->forget(PublicCache::CAROUSEL, PublicCache::HOMEPAGE);

        return redirect()->route('admin.settings.carousel.index')->with('success', 'Slide berhasil dihapus.');
    }

    /**
     * Copies a slide as a draft.
     *
     * The copy is switched off and its image file is duplicated rather than
     * shared, so editing or deleting one never disturbs the other.
     */
    public function duplicate(CarouselSlide $carousel): RedirectResponse
    {
        $this->authorize('create', CarouselSlide::class);

        $copy = $carousel->replicate(['created_at', 'updated_at']);
        $copy->title = trim(($carousel->title ?: 'Slide').' (salinan)');
        $copy->is_active = false;
        $copy->sort_order = (int) (CarouselSlide::max('sort_order') + 10);
        $copy->image = $this->media->duplicatePublic($carousel->image, self::IMAGE_DIRECTORY) ?? $carousel->image;
        $copy->save();

        $this->audit->recordModel('create', 'carousel', $copy);
        $this->cache->forget(PublicCache::CAROUSEL, PublicCache::HOMEPAGE);

        return redirect()
            ->route('admin.settings.carousel.edit', $copy)
            ->with('success', 'Slide disalin sebagai draf yang belum aktif.');
    }

    public function toggle(CarouselSlide $carousel): RedirectResponse
    {
        $this->authorize('update', $carousel);

        $carousel->update(['is_active' => ! $carousel->is_active]);

        $this->audit->record($carousel->is_active ? 'activate' : 'deactivate', 'carousel', $carousel->getKey());
        $this->cache->forget(PublicCache::CAROUSEL, PublicCache::HOMEPAGE);

        return back()->with('success', $carousel->is_active ? 'Slide diaktifkan.' : 'Slide dinonaktifkan.');
    }

    /** Persists a new order in one atomic request, as the homepage sections do. */
    public function reorder(Request $request): JsonResponse
    {
        $this->authorize('update', new CarouselSlide());

        $validated = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer', 'exists:carousel_slides,id'],
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['order'] as $position => $id) {
                CarouselSlide::whereKey($id)->update(['sort_order' => ($position + 1) * 10]);
            }
        });

        $this->audit->record('reorder', 'carousel', null, null, ['order' => $validated['order']]);
        $this->cache->forget(PublicCache::CAROUSEL, PublicCache::HOMEPAGE);

        return response()->json(['success' => true, 'message' => 'Urutan slide disimpan.']);
    }

    /**
     * The hero exactly as the public will see it, including slides that are
     * scheduled or switched off — the point is to check a slide before
     * activating it.
     */
    public function preview(Request $request): View
    {
        $this->authorize('viewAny', CarouselSlide::class);

        $slides = CarouselSlide::query()
            ->when($request->filled('slide'), fn ($query) => $query->whereKey($request->integer('slide')))
            ->orderBy('sort_order')
            ->get();

        return view('admin.settings.carousel.preview', ['slides' => $slides]);
    }
}


<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AnnouncementRequest;
use App\Models\Announcement;
use App\Services\Audit\AuditLogger;
use App\Services\Cache\PublicCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class AnnouncementController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly PublicCache $cache,
    ) {
    }

    public function index(): View
    {
        $this->authorize('viewAny', Announcement::class);

        return view('admin.announcements.index', [
            'announcements' => Announcement::orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Announcement::class);

        $query = Announcement::query()->select('announcements.*');

        if ($request->filled('status')) {
            $query->where('is_active', $request->string('status')->toString() === 'active');
        }

        return DataTables::eloquent($query)
            ->editColumn('title', fn (Announcement $a) => e($a->title))
            ->addColumn('ticker', fn (Announcement $a) => e($a->tickerText()))
            ->addColumn('code', fn (Announcement $a) => $a->code ? '<code class="rounded bg-muted px-1.5 py-0.5 text-xs">'.e($a->code).'</code>' : '—')
            // The flag alone would read "active" for a notice whose window has
            // closed, so the badge reports what a visitor actually sees.
            ->addColumn('status', fn (Announcement $a) => view('admin.announcements.partials.status', ['announcement' => $a])->render())
            ->editColumn('start_date', fn (Announcement $a) => $a->start_date?->translatedFormat('d M Y') ?? 'Kapan saja')
            ->editColumn('end_date', fn (Announcement $a) => $a->end_date?->translatedFormat('d M Y') ?? 'Seterusnya')
            ->addColumn('actions', fn (Announcement $a) => view('admin.announcements.partials.actions', ['announcement' => $a])->render())
            ->rawColumns(['code', 'status', 'actions'])
            ->toJson();
    }

    public function create(): View
    {
        $this->authorize('create', Announcement::class);

        return view('admin.announcements.form', [
            'announcement' => new Announcement(['is_active' => true, 'button_text' => 'Selengkapnya']),
        ]);
    }

    public function store(AnnouncementRequest $request): RedirectResponse
    {
        $this->authorize('create', Announcement::class);

        $announcement = new Announcement($request->validated());
        $announcement->is_active = $request->boolean('is_active');
        $announcement->sort_order = (int) (Announcement::max('sort_order') + 10);
        $announcement->save();

        $this->audit->recordModel('create', 'announcement', $announcement);
        $this->cache->forget(PublicCache::ANNOUNCEMENTS);

        return redirect()->route('admin.announcements.index')->with('success', 'Pengumuman berhasil ditambahkan.');
    }

    public function edit(Announcement $announcement): View
    {
        $this->authorize('update', $announcement);

        return view('admin.announcements.form', ['announcement' => $announcement]);
    }

    public function update(AnnouncementRequest $request, Announcement $announcement): RedirectResponse
    {
        $this->authorize('update', $announcement);

        $original = $announcement->getOriginal();
        $announcement->fill($request->validated());
        $announcement->is_active = $request->boolean('is_active');
        $announcement->save();

        $this->audit->recordModel('update', 'announcement', $announcement, $original);
        $this->cache->forget(PublicCache::ANNOUNCEMENTS);

        return redirect()->route('admin.announcements.index')->with('success', 'Pengumuman berhasil diperbarui.');
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        $this->authorize('delete', $announcement);

        $announcement->delete();

        $this->audit->record('delete', 'announcement', $announcement->getKey());
        $this->cache->forget(PublicCache::ANNOUNCEMENTS);

        return redirect()->route('admin.announcements.index')->with('success', 'Pengumuman berhasil dihapus.');
    }

    public function toggle(Announcement $announcement): RedirectResponse
    {
        $this->authorize('update', $announcement);

        $announcement->update(['is_active' => ! $announcement->is_active]);

        $this->audit->record($announcement->is_active ? 'activate' : 'deactivate', 'announcement', $announcement->getKey());
        $this->cache->forget(PublicCache::ANNOUNCEMENTS);

        return back()->with('success', $announcement->is_active ? 'Pengumuman diaktifkan.' : 'Pengumuman dinonaktifkan.');
    }

    /** Persists a new order in one atomic request, as the carousel does. */
    public function reorder(Request $request): JsonResponse
    {
        $this->authorize('update', new Announcement());

        $validated = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer', 'exists:announcements,id'],
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['order'] as $position => $id) {
                Announcement::whereKey($id)->update(['sort_order' => ($position + 1) * 10]);
            }
        });

        $this->audit->record('reorder', 'announcement', null, null, ['order' => $validated['order']]);
        $this->cache->forget(PublicCache::ANNOUNCEMENTS);

        return response()->json(['success' => true, 'message' => 'Urutan pengumuman disimpan.']);
    }

    /**
     * The modal and ticker exactly as a visitor would meet them, including
     * notices that are switched off or still scheduled.
     */
    public function preview(): View
    {
        $this->authorize('viewAny', Announcement::class);

        return view('admin.announcements.preview', [
            'announcements' => Announcement::orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }
}

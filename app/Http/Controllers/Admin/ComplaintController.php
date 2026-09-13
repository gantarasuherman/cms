<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\ComplaintAttachment;
use App\Models\ComplaintCategory;
use App\Services\Audit\AuditLogger;
use App\Services\Bot\Actions\ReporterReplier;
use App\Services\Complaints\LocationClusters;
use App\Services\Media\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Yajra\DataTables\Facades\DataTables;

class ComplaintController extends Controller
{
    public function __construct(
        private readonly MediaService $media,
        private readonly AuditLogger $audit,
        private readonly ReporterReplier $replier,
    ) {
    }

    public function index(): View
    {
        $this->authorize('viewAny', Complaint::class);

        return view('admin.complaints.index', [
            'categories' => ComplaintCategory::active()->get(),
            'statuses' => Complaint::STATUSES,
            'counts' => Complaint::query()
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
        ]);
    }

    /**
     * Where the trouble is.
     *
     * A list of complaints answers "what came in"; this answers "where is the
     * same thing being reported over and over", which is the question that
     * decides where a crew goes on Monday.
     */
    public function map(Request $request, LocationClusters $clusters): View
    {
        $this->authorize('viewAny', Complaint::class);

        $radius = (int) $request->integer('radius', LocationClusters::DEFAULT_RADIUS);
        $radius = max(50, min(2000, $radius));

        $complaints = Complaint::query()
            ->with('category')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('kategori'), fn ($q) => $q->where('complaint_category_id', $request->integer('kategori')))
            ->get();

        $found = $clusters->build($complaints, $radius);

        return view('admin.complaints.map', [
            'clusters' => $found,
            // Handed to the map as data rather than drawn server-side: the
            // clustering is the analysis, and the browser only renders it.
            'markers' => $found->map(fn ($cluster) => [
                'lat' => $cluster->centre()[0],
                'lng' => $cluster->centre()[1],
                'count' => $cluster->count(),
                'label' => $cluster->label(),
                'summary' => $cluster->summary(),
                'complaints' => $cluster->complaints->sortByDesc('created_at')->take(20)->map(fn ($c) => [
                    'ticket' => $c->ticket,
                    'category' => $c->category?->name ?: 'Tanpa kategori',
                    'status' => $c->statusLabel(),
                    'date' => $c->created_at->translatedFormat('d M Y'),
                    'url' => route('admin.complaints.show', $c),
                ])->values(),
            ])->values(),
            'radius' => $radius,
            'located' => $complaints->count(),
            'total' => Complaint::count(),
            'categories' => ComplaintCategory::active()->get(),
            'statuses' => Complaint::STATUSES,
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Complaint::class);

        // `select()` before `withCount()`: calling it after replaces the
        // select list and silently drops the count.
        $query = Complaint::query()
            ->select('complaints.*')
            ->with(['category', 'contact'])
            ->withCount('attachments');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('category')) {
            $query->where('complaint_category_id', $request->integer('category'));
        }

        if ($request->filled('channel')) {
            $query->where('channel', $request->string('channel')->toString());
        }

        return DataTables::eloquent($query)
            ->editColumn('ticket', fn (Complaint $c) => '<code class="font-mono text-xs">'.e($c->ticket).'</code>')
            ->addColumn('category_name', fn (Complaint $c) => e($c->category?->name ?? '—'))
            ->editColumn('description', fn (Complaint $c) => e(\Illuminate\Support\Str::limit($c->description, 80)))
            ->addColumn('reporter', fn (Complaint $c) => e($c->contact?->displayName() ?? $c->reporter_name ?? '—'))
            ->addColumn('status_badge', fn (Complaint $c) => view('admin.complaints.partials.status', ['complaint' => $c])->render())
            ->addColumn('evidence', fn (Complaint $c) => view('admin.complaints.partials.evidence', ['complaint' => $c])->render())
            ->editColumn('created_at', fn (Complaint $c) => $c->created_at?->translatedFormat('d M Y H:i'))
            ->addColumn('actions', fn (Complaint $c) => view('admin.complaints.partials.actions', ['complaint' => $c])->render())
            ->rawColumns(['ticket', 'status_badge', 'evidence', 'actions'])
            ->toJson();
    }

    public function show(Complaint $complaint): View
    {
        $this->authorize('view', $complaint);

        return view('admin.complaints.show', [
            'complaint' => $complaint->load(['category', 'contact', 'assignee', 'attachments.uploader', 'updates.user', 'conversation.messages']),
            'statuses' => Complaint::STATUSES,
        ]);
    }

    /**
     * Moves a complaint along, optionally with proof of the work.
     *
     * The change and its photographs are written together: a complaint marked
     * finished whose proof failed to save is worse than one not yet marked,
     * because the reporter has already been told it is done.
     */
    public function update(Request $request, Complaint $complaint): RedirectResponse
    {
        $this->authorize('update', $complaint);

        $data = $request->validate([
            'status' => ['required', 'string', Rule::in(array_keys(Complaint::STATUSES))],
            'note' => ['nullable', 'string', 'max:1000'],
            'notify_reporter' => ['nullable', 'boolean'],
            'proof' => ['nullable', 'array', 'max:5'],
            'proof.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:6144'],
        ]);

        $from = $complaint->status;

        DB::transaction(function () use ($request, $complaint, $data, $from) {
            $complaint->status = $data['status'];

            if ($data['status'] === 'diproses') {
                $complaint->processed_at ??= now();
            }

            $complaint->resolved_at = $data['status'] === 'selesai' ? now() : null;
            $complaint->save();

            foreach ($request->file('proof', []) as $file) {
                ComplaintAttachment::create([
                    'complaint_id' => $complaint->getKey(),
                    // Proof of the work, not another copy of the report.
                    'kind' => 'resolution',
                    'path' => $this->media->storePrivate($file, 'complaints'),
                    'mime' => $file->getMimeType(),
                    'size' => $file->getSize(),
                    'uploaded_by' => $request->user()->getKey(),
                ]);
            }

            $complaint->updates()->create([
                'from_status' => $from,
                'to_status' => $data['status'],
                'note' => $data['note'] ?? null,
                'user_id' => $request->user()->getKey(),
                'source' => 'admin',
            ]);
        });

        $this->audit->record('update', 'complaint', $complaint->getKey(), ['status' => $from], ['status' => $data['status']]);

        $message = 'Status pengaduan diperbarui menjadi '.$complaint->statusLabel().'.';

        if ($request->boolean('notify_reporter')) {
            $sent = $this->replier->send(
                $complaint,
                $this->replier->statusMessage($complaint, $data['note'] ?? null),
                by: $request->user(),
            );

            $message .= $sent
                ? ' Pelapor diberi tahu.'
                : ' Pelapor tidak dapat dihubungi — pengaduan ini tidak berasal dari kanal chat.';
        }

        return back()->with('success', $message);
    }

    /**
     * Writes back to the reporter from the panel.
     *
     * Until now an operator's note only reached the internal history, and the
     * person who filed the report heard nothing unless an officer happened to
     * use a command from their own phone.
     */
    public function reply(Request $request, Complaint $complaint): RedirectResponse
    {
        $this->authorize('update', $complaint);

        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'attachment' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:6144'],
        ], [
            'message.required' => 'Tulis dulu pesannya.',
        ]);

        $path = $request->hasFile('attachment')
            ? $this->media->storePrivate($request->file('attachment'), 'complaints')
            : null;

        $sent = $this->replier->send($complaint, $data['message'], $path, $request->user());

        if (! $sent) {
            return back()->with('warning', 'Pengaduan ini tidak berasal dari WhatsApp atau Telegram, jadi tidak ada tujuan untuk membalas.');
        }

        $this->audit->record('reply', 'complaint', $complaint->getKey());

        return back()->with('success', 'Balasan dikirim ke pelapor.');
    }

    public function destroy(Complaint $complaint): RedirectResponse
    {
        $this->authorize('delete', $complaint);

        foreach ($complaint->attachments as $attachment) {
            $this->media->delete($attachment->path, 'local');
        }

        $complaint->delete();

        $this->audit->record('delete', 'complaint', $complaint->getKey());

        return redirect()->route('admin.complaints.index')->with('success', 'Pengaduan dihapus.');
    }

    /**
     * Serves one attachment.
     *
     * Through a route, never a public disk URL: these are photographs of
     * somebody's street, house or property, sent privately. The id is checked
     * against the viewer's permission, so knowing a number is not access — the
     * documents module makes the same choice for the same reason.
     */
    public function attachment(ComplaintAttachment $attachment): StreamedResponse
    {
        $this->authorize('view', $attachment->complaint);

        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        return Storage::disk('local')->response(
            $attachment->path,
            basename($attachment->path),
            // Worked out from the file when nothing useful was recorded, or the
            // browser downloads a photograph instead of showing it.
            ['Content-Type' => MediaService::mimeFor($attachment->path, $attachment->mime)],
        );
    }
}

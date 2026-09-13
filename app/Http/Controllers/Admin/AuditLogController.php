<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\Cache\PublicCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class AuditLogController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', AuditLog::class);

        return view('admin.audit-logs.index', [
            'modules' => AuditLog::query()->distinct()->orderBy('module')->pluck('module'),
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AuditLog::class);

        $query = AuditLog::query()->with('user:id,name')->select('audit_logs.*');

        foreach (['module', 'action'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->string($filter));
            }
        }

        // Date filtering is inclusive of the whole end day, which is what
        // "sampai tanggal X" means to the person reading the screen.
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->date('to'));
        }

        return DataTables::eloquent($query)
            ->addColumn('user_name', fn (AuditLog $log) => $log->user?->name ?? 'Sistem')
            ->editColumn('created_at', fn (AuditLog $log) => $log->created_at?->translatedFormat('d M Y H:i:s'))
            ->addColumn('changes', fn (AuditLog $log) => view('admin.audit-logs.partials.changes', ['log' => $log])->render())
            ->filterColumn('user_name', fn ($q, $keyword) => $q->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$keyword}%")))
            ->rawColumns(['changes'])
            ->toJson();
    }

    public function show(AuditLog $auditLog): View
    {
        $this->authorize('view', $auditLog);

        return view('admin.audit-logs.show', ['log' => $auditLog->load('user:id,name')]);
    }

    /**
     * Clears the public caches by hand.
     *
     * Caches are invalidated automatically whenever content changes; this is
     * the escape hatch for when something was changed outside the CMS, such as
     * a direct database edit during a migration.
     */
    public function clearCache(PublicCache $cache): RedirectResponse
    {
        $this->authorize('viewAny', AuditLog::class);

        $cache->flushAll();

        return back()->with('success', 'Cache publik berhasil dikosongkan.');
    }
}


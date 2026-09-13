<?php

namespace App\Http\Controllers\Admin\Services;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ServiceRequest;
use App\Models\Category;
use App\Models\Service;
use App\Services\Services\ServiceWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class ServiceController extends Controller
{
    public function __construct(private readonly ServiceWriter $writer)
    {
    }

    public function index(): View
    {
        $this->authorize('viewAny', Service::class);

        return view('admin.services.index', [
            'categories' => Category::ofType(Category::TYPE_SERVICE)->ordered()->pluck('name', 'id'),
            'statuses' => $this->statusOptions(),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Service::class);

        $query = Service::query()->with('category')->withCount(['requirements', 'tariffs', 'steps'])->select('services.*');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('category')) {
            $query->where('category_id', $request->integer('category'));
        }

        return DataTables::eloquent($query)
            ->addColumn('category_name', fn (Service $service) => $service->category?->name ?? '—')
            ->addColumn('detail_counts', fn (Service $service) => sprintf(
                '%d syarat · %d tarif · %d tahap',
                $service->requirements_count,
                $service->tariffs_count,
                $service->steps_count,
            ))
            ->editColumn('status', fn (Service $service) => view('components.status-badge', ['status' => $service->status])->render())
            ->editColumn('processing_time', fn (Service $service) => $service->processing_time ?: '—')
            ->addColumn('actions', fn (Service $service) => view('admin.services.partials.actions', ['service' => $service])->render())
            ->filterColumn('category_name', fn ($query, $keyword) => $query->whereHas('category', fn ($q) => $q->where('name', 'like', "%{$keyword}%")))
            ->rawColumns(['status', 'actions'])
            ->toJson();
    }

    public function create(): View
    {
        $this->authorize('create', Service::class);

        return view('admin.services.form', $this->formData(new Service(['status' => Service::STATUS_DRAFT])));
    }

    public function store(ServiceRequest $request): RedirectResponse
    {
        $this->authorize('create', Service::class);

        $service = $this->writer->create($request->validated(), $request->file('image'));

        return redirect()
            ->route('admin.services.edit', $service)
            ->with('success', 'Layanan berhasil dibuat. Lanjutkan dengan persyaratan, tarif, dan tahapan.');
    }

    public function show(Service $service): RedirectResponse
    {
        $this->authorize('view', $service);

        return redirect()->route('admin.services.edit', $service);
    }

    public function edit(Service $service): View
    {
        $this->authorize('update', $service);

        return view('admin.services.form', $this->formData($service));
    }

    public function update(ServiceRequest $request, Service $service): RedirectResponse
    {
        $this->authorize('update', $service);

        $this->writer->update($service, $request->validated(), $request->file('image'));

        return redirect()
            ->route('admin.services.edit', $service)
            ->with('success', 'Layanan berhasil diperbarui.');
    }

    public function destroy(Service $service): RedirectResponse
    {
        $this->authorize('delete', $service);

        $this->writer->delete($service);

        return redirect()
            ->route('admin.services.index')
            ->with('success', 'Layanan berhasil dihapus.');
    }

    /** @return array<string, mixed> */
    private function formData(Service $service): array
    {
        return [
            'service' => $service,
            'categories' => Category::ofType(Category::TYPE_SERVICE)->ordered()->pluck('name', 'id'),
            'statuses' => $this->statusOptions(),
        ];
    }

    /** @return array<string, string> */
    private function statusOptions(): array
    {
        return [
            Service::STATUS_DRAFT => 'Draf',
            Service::STATUS_PUBLISHED => 'Terbit',
            Service::STATUS_ARCHIVED => 'Arsip',
        ];
    }
}


<?php

namespace App\Http\Controllers\Admin\Services;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Services\Audit\AuditLogger;
use App\Services\Cache\PublicCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Shared flow for the three collections that hang off a service —
 * requirements, tariffs and steps.
 *
 * They differ only in model, fields and wording, while the parts that must not
 * drift (authorization against the parent service, scoping every row to its
 * own service, audit trail, cache invalidation) live here once.
 */
abstract class ServiceChildController extends Controller
{
    public function __construct(
        protected readonly AuditLogger $audit,
        protected readonly PublicCache $cache,
    ) {
    }

    /** Relation name on Service, e.g. "requirements". */
    abstract protected function relation(): string;

    /** Blade view namespace, e.g. "admin.services.requirements". */
    abstract protected function viewNamespace(): string;

    /** Route name prefix, e.g. "admin.services.requirements". */
    abstract protected function routeName(): string;

    /** Module name recorded in the audit log. */
    abstract protected function auditModule(): string;

    abstract protected function newModel(): Model;

    public function index(Service $service): View
    {
        $this->authorize('view', $service);

        return view($this->viewNamespace().'.index', [
            'service' => $service,
            'items' => $service->{$this->relation()}()->get(),
            'item' => $this->newModel(),
        ]);
    }

    /**
     * Shared write path. Takes already-validated data rather than the request
     * object: PHP forbids narrowing a parameter type in an override, so each
     * subclass declares its own FormRequest and hands the result to this.
     *
     * @param  array<string, mixed>  $data
     */
    protected function storeChild(array $data, Service $service): RedirectResponse
    {
        $this->authorize('update', $service);

        $item = $service->{$this->relation()}()->create($data);

        $this->audit->recordModel('create', $this->auditModule(), $item);
        $this->cache->forget(PublicCache::SERVICES);

        return redirect()
            ->route($this->routeName().'.index', $service)
            ->with('success', 'Data berhasil ditambahkan.');
    }

    public function edit(Service $service, int $id): View
    {
        $this->authorize('update', $service);

        return view($this->viewNamespace().'.edit', [
            'service' => $service,
            'item' => $this->findScoped($service, $id),
        ]);
    }

    /** @param array<string, mixed> $data */
    protected function updateChild(array $data, Service $service, int $id): RedirectResponse
    {
        $this->authorize('update', $service);

        $item = $this->findScoped($service, $id);
        $original = $item->getOriginal();
        $item->update($data);

        $this->audit->recordModel('update', $this->auditModule(), $item, $original);
        $this->cache->forget(PublicCache::SERVICES);

        return redirect()
            ->route($this->routeName().'.index', $service)
            ->with('success', 'Data berhasil diperbarui.');
    }

    public function destroy(Service $service, int $id): RedirectResponse
    {
        $this->authorize('update', $service);

        $this->findScoped($service, $id)->delete();

        $this->audit->record('delete', $this->auditModule(), $id);
        $this->cache->forget(PublicCache::SERVICES);

        return redirect()
            ->route($this->routeName().'.index', $service)
            ->with('success', 'Data berhasil dihapus.');
    }

    /**
     * Resolves a child row *through* its parent rather than by id alone, so
     * /admin/services/{a}/tariffs/{id-belonging-to-b} is a 404 instead of a
     * cross-service edit.
     */
    protected function findScoped(Service $service, int $id): Model
    {
        return $service->{$this->relation()}()->findOrFail($id);
    }
}


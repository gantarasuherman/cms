<?php

namespace App\Http\Controllers\Admin\Services;

use App\Http\Requests\Admin\ServiceRequirementRequest;
use App\Models\Service;
use App\Models\ServiceRequirement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;

class RequirementController extends ServiceChildController
{
    protected function relation(): string
    {
        return 'requirements';
    }

    protected function viewNamespace(): string
    {
        return 'admin.services.requirements';
    }

    protected function routeName(): string
    {
        return 'admin.services.requirements';
    }

    protected function auditModule(): string
    {
        return 'service_requirement';
    }

    protected function newModel(): Model
    {
        return new ServiceRequirement();
    }

    // The concrete request type lives here so Laravel validates the right
    // rules; the shared flow then runs on the validated data.
    public function store(ServiceRequirementRequest $request, Service $service): RedirectResponse
    {
        return $this->storeChild($request->validated(), $service);
    }

    public function update(ServiceRequirementRequest $request, Service $service, int $id): RedirectResponse
    {
        return $this->updateChild($request->validated(), $service, $id);
    }
}


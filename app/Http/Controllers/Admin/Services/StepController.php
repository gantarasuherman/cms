<?php

namespace App\Http\Controllers\Admin\Services;

use App\Http\Requests\Admin\ServiceStepRequest;
use App\Models\Service;
use App\Models\ServiceStep;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;

class StepController extends ServiceChildController
{
    protected function relation(): string
    {
        return 'steps';
    }

    protected function viewNamespace(): string
    {
        return 'admin.services.steps';
    }

    protected function routeName(): string
    {
        return 'admin.services.steps';
    }

    protected function auditModule(): string
    {
        return 'service_step';
    }

    protected function newModel(): Model
    {
        return new ServiceStep();
    }

    // The concrete request type lives here so Laravel validates the right
    // rules; the shared flow then runs on the validated data.
    public function store(ServiceStepRequest $request, Service $service): RedirectResponse
    {
        return $this->storeChild($request->validated(), $service);
    }

    public function update(ServiceStepRequest $request, Service $service, int $id): RedirectResponse
    {
        return $this->updateChild($request->validated(), $service, $id);
    }
}


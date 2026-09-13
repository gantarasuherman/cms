<?php

namespace App\Http\Controllers\Admin\Services;

use App\Http\Requests\Admin\ServiceTariffRequest;
use App\Models\Service;
use App\Models\ServiceTariff;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;

class TariffController extends ServiceChildController
{
    protected function relation(): string
    {
        return 'tariffs';
    }

    protected function viewNamespace(): string
    {
        return 'admin.services.tariffs';
    }

    protected function routeName(): string
    {
        return 'admin.services.tariffs';
    }

    protected function auditModule(): string
    {
        return 'service_tariff';
    }

    protected function newModel(): Model
    {
        return new ServiceTariff();
    }

    // The concrete request type lives here so Laravel validates the right
    // rules; the shared flow then runs on the validated data.
    public function store(ServiceTariffRequest $request, Service $service): RedirectResponse
    {
        return $this->storeChild($request->validated(), $service);
    }

    public function update(ServiceTariffRequest $request, Service $service, int $id): RedirectResponse
    {
        return $this->updateChild($request->validated(), $service, $id);
    }
}


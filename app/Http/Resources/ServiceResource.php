<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin \App\Models\Service */
class ServiceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $detail = $request->routeIs('api.public.services.show');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'content' => $this->when($detail, $this->content),
            'icon' => $this->icon,
            'image' => $this->image ? Storage::disk('public')->url($this->image) : null,
            'processing_time' => $this->processing_time,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'requirements' => RequirementResource::collection($this->whenLoaded('requirements')),
            'tariffs' => TariffResource::collection($this->whenLoaded('tariffs')),
            'steps' => StepResource::collection($this->whenLoaded('steps')),
            'total_tariff' => $this->when(
                $this->relationLoaded('tariffs'),
                fn () => (float) $this->totalTariff(),
            ),
            'url' => route('public.services.show', $this->slug),
        ];
    }
}


<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Category */
class CategoryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'icon' => $this->icon,
            'count' => $this->whenNotNull(
                $this->news_count ?? $this->services_count ?? $this->documents_count ?? $this->faqs_count,
            ),
            'children' => CategoryResource::collection($this->whenLoaded('children')),
        ];
    }
}


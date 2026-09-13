<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\PublicMenu */
class MenuResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'icon' => $this->icon,
            'url' => $this->link(),
            'target' => $this->target,
            'sort_order' => $this->sort_order,
            'children' => MenuResource::collection($this->loadedChildren()),
        ];
    }
}


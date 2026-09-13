<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\CarouselSlide */
class CarouselResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category' => $this->category,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'description' => $this->description,
            'image' => $this->imageUrl(),
            'alt_text' => $this->alt_text,
            'link' => $this->link,
            'button_text' => $this->button_text,
            'sort_order' => $this->sort_order,
        ];
    }
}


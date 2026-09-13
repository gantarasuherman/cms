<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin \App\Models\News */
class NewsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            // Full body only on a detail response: list endpoints would
            // otherwise ship every article in full.
            'content' => $this->when($request->routeIs('api.public.news.show'), $this->content),
            'featured_image' => $this->featured_image ? Storage::disk('public')->url($this->featured_image) : null,
            'is_featured' => $this->is_featured,
            'views' => $this->views,
            'published_at' => $this->published_at?->toIso8601String(),
            'author' => $this->whenLoaded('author', fn () => [
                'id' => $this->author?->id,
                'name' => $this->author?->name,
            ]),
            'categories' => CategoryResource::collection($this->whenLoaded('categories')),
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->map(fn ($tag) => [
                'name' => $tag->name,
                'slug' => $tag->slug,
            ])),
            'url' => route('public.news.show', $this->slug),
        ];
    }
}


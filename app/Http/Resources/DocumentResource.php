<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Document */
class DocumentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'extension' => $this->file_extension,
            'size' => $this->file_size,
            'download_count' => $this->download_count,
            'published_at' => $this->published_at?->toIso8601String(),
            'category' => new CategoryResource($this->whenLoaded('category')),
            // The controlled route, never the storage path: the stored file is
            // not reachable any other way.
            'download_url' => route('public.documents.download', $this->slug),
            'url' => route('public.documents.show', $this->slug),
        ];
    }
}


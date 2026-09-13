<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CarouselSlide extends Model
{
    protected $fillable = [
        'title', 'subtitle', 'category', 'description', 'image', 'alt_text',
        'link', 'button_text', 'sort_order', 'start_date', 'end_date', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'datetime',
            'end_date' => 'datetime',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** Active and inside its optional scheduling window. */
    public function scopeLive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('start_date')->orWhere('start_date', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('end_date')->orWhere('end_date', '>=', now()))
            ->orderBy('sort_order');
    }

    public function imageUrl(): string
    {
        return Storage::disk('public')->url($this->image);
    }

    /**
     * What a screen reader hears in place of the photograph.
     *
     * Empty when the slide has no alt text of its own: the heading and
     * description beside it already carry the meaning, and repeating them as
     * alt text would have the image announced twice.
     */
    public function altText(): string
    {
        return (string) ($this->alt_text ?? '');
    }

    /** True while the slide is within its window and switched on. */
    public function isLive(): bool
    {
        return $this->is_active
            && ($this->start_date === null || $this->start_date->isPast())
            && ($this->end_date === null || $this->end_date->isFuture());
    }
}


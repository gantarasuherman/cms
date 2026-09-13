<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A site-wide notice. It has two faces of the same record: a modal shown once
 * to a visitor, and a permanent ticker line above the header that survives the
 * modal being dismissed.
 */
class Announcement extends Model
{
    protected $fillable = [
        'badge', 'title', 'body', 'code', 'button_text', 'link',
        'ticker_text', 'sort_order', 'start_date', 'end_date', 'is_active',
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
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function isLive(): bool
    {
        return $this->is_active
            && ($this->start_date === null || $this->start_date->isPast())
            && ($this->end_date === null || $this->end_date->isFuture());
    }

    /**
     * The one line the ticker shows.
     *
     * Falls back to the title so a record can never contribute an empty strip —
     * the ticker would then rotate to a blank row for several seconds.
     */
    public function tickerText(): string
    {
        return trim((string) ($this->ticker_text ?: $this->title));
    }

    /** True when there is something for the modal to show beyond its heading. */
    public function hasDetail(): bool
    {
        return filled($this->body) || filled($this->code) || $this->hasAction();
    }

    public function hasAction(): bool
    {
        return filled($this->link) && filled($this->button_text);
    }
}

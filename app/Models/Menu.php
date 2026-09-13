<?php

namespace App\Models;

use App\Models\Concerns\HasSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Shared behaviour for the two navigation trees (admin and public).
 * Both are unlimited-depth, database-driven and resolve their href from
 * either a named route or a raw URL.
 */
abstract class Menu extends Model
{
    use HasSlug;

    protected $fillable = [
        'parent_id', 'title', 'slug', 'icon', 'route', 'url', 'sort_order', 'is_active', 'target',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    protected function slugSourceColumn(): string
    {
        return 'title';
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(static::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(static::class, 'parent_id')->orderBy('sort_order');
    }

    /** Eager-loads the whole subtree so rendering never issues per-node queries. */
    public function childrenRecursive(): HasMany
    {
        return $this->children()->with('childrenRecursive');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    /** @return Collection<int, static> */
    public function loadedChildren(): Collection
    {
        return $this->relationLoaded('childrenRecursive')
            ? $this->childrenRecursive
            : $this->children;
    }

    /**
     * Resolved href. A named route wins over a raw URL; an unresolvable route
     * degrades to '#' rather than throwing and breaking the whole navigation.
     */
    public function link(): string
    {
        if (filled($this->route)) {
            try {
                return route($this->route);
            } catch (\Throwable) {
                return '#';
            }
        }

        return filled($this->url) ? $this->url : '#';
    }

    public function isCurrent(): bool
    {
        $link = $this->link();

        if ($link === '#') {
            return false;
        }

        return request()->url() === $link || request()->fullUrlIs($link.'/*');
    }

    /** True when this node, or anything beneath it, matches the current request. */
    public function isActiveTrail(): bool
    {
        if ($this->isCurrent()) {
            return true;
        }

        foreach ($this->loadedChildren() as $child) {
            if ($child->isActiveTrail()) {
                return true;
            }
        }

        return false;
    }
}


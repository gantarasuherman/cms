<?php

namespace App\Models;

use App\Models\Concerns\HasSlug;
use App\Models\Concerns\Publishable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use HasFactory, HasSlug, Publishable, SoftDeletes;

    protected $fillable = [
        'category_id', 'name', 'slug', 'description', 'content', 'icon', 'image',
        'processing_time', 'status', 'sort_order', 'seo_title', 'seo_description', 'seo_keywords',
    ];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    /** Services carry no publication date: they are either published or not. */
    protected function publishedAtColumn(): ?string
    {
        return null;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(ServiceRequirement::class)->orderBy('sort_order');
    }

    public function tariffs(): HasMany
    {
        return $this->hasMany(ServiceTariff::class)->orderBy('sort_order');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ServiceStep::class)->orderBy('sort_order');
    }

    public function totalTariff(): string
    {
        return (string) $this->tariffs->where('is_active', true)->sum('amount');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}


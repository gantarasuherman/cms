<?php

namespace App\Models;

use App\Models\Concerns\HasSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A single taxonomy table shared by every module. The `type` column keeps
 * news / service / document / faq trees isolated from each other while the
 * behaviour (nesting, ordering, slugs) stays owned in one place.
 */
class Category extends Model
{
    use HasFactory, HasSlug, SoftDeletes;

    public const TYPE_NEWS = 'news';

    public const TYPE_SERVICE = 'service';

    public const TYPE_DOCUMENT = 'document';

    public const TYPE_FAQ = 'faq';

    protected $fillable = [
        'type', 'parent_id', 'name', 'slug', 'description', 'icon', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @return array<int, string> */
    public static function types(): array
    {
        return [self::TYPE_NEWS, self::TYPE_SERVICE, self::TYPE_DOCUMENT, self::TYPE_FAQ];
    }

    protected function slugUniqueScope(): array
    {
        return ['type' => $this->type];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function news(): BelongsToMany
    {
        return $this->belongsToMany(News::class, 'news_categories');
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function faqs(): HasMany
    {
        return $this->hasMany(Faq::class);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}


<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Generates a unique slug from a source attribute when none is supplied.
 * Uniqueness is scoped by {@see slugUniqueScope()} so that, for example,
 * categories only need a slug unique within their own type.
 */
trait HasSlug
{
    public static function bootHasSlug(): void
    {
        static::saving(function (self $model) {
            if (blank($model->slug)) {
                $model->slug = $model->generateUniqueSlug((string) $model->{$model->slugSourceColumn()});
            }
        });
    }

    protected function slugSourceColumn(): string
    {
        return 'name';
    }

    /** @return array<string, mixed> Additional where-clauses constraining slug uniqueness. */
    protected function slugUniqueScope(): array
    {
        return [];
    }

    public function generateUniqueSlug(string $source): string
    {
        $base = Str::slug($source) ?: Str::random(8);
        $slug = $base;
        $suffix = 1;

        while ($this->slugExists($slug)) {
            $slug = $base.'-'.(++$suffix);
        }

        return $slug;
    }

    protected function slugExists(string $slug): bool
    {
        $query = static::query()->where('slug', $slug);

        foreach ($this->slugUniqueScope() as $column => $value) {
            $query->where($column, $value);
        }

        if ($this->exists) {
            $query->whereKeyNot($this->getKey());
        }

        if (in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($this), true)) {
            $query->withTrashed();
        }

        return $query->exists();
    }
}


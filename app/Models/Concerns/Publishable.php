<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Shared publication lifecycle for content entities (draft / published / archived).
 */
trait Publishable
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_ARCHIVED = 'archived';

    /** @return array<int, string> */
    public static function statuses(): array
    {
        return [self::STATUS_DRAFT, self::STATUS_PUBLISHED, self::STATUS_ARCHIVED];
    }

    /**
     * Column carrying the publication timestamp, or null for entities that
     * have no scheduling at all.
     *
     * Declared rather than assumed: services are published or not, with no
     * publish date, and a trait must not reach for a column its host does not
     * have.
     */
    protected function publishedAtColumn(): ?string
    {
        return 'published_at';
    }

    public function scopePublished(Builder $query): Builder
    {
        $query->where('status', self::STATUS_PUBLISHED);

        if ($column = $this->publishedAtColumn()) {
            $query->where(function (Builder $q) use ($column) {
                $q->whereNull($column)->orWhere($column, '<=', now());
            });
        }

        return $query;
    }

    public function isPublished(): bool
    {
        if ($this->status !== self::STATUS_PUBLISHED) {
            return false;
        }

        $column = $this->publishedAtColumn();

        return $column === null || $this->{$column} === null || $this->{$column}->isPast();
    }
}


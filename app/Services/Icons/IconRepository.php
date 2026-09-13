<?php

namespace App\Services\Icons;

use App\Models\Icon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * The icon catalogue.
 *
 * Icons live in the database so administrators pick from a real list instead
 * of typing a name and hoping. Rendering still has to be cheap — a sidebar
 * draws a couple of dozen icons per page — so the whole name-to-body map is
 * held in one cache entry rather than queried per icon.
 *
 * config/icons.php remains the seed source and the fallback: before the table
 * is seeded, or if the database is unreachable while a page renders, icons
 * still draw instead of leaving holes in the interface.
 */
class IconRepository
{
    private const CACHE_KEY = 'icons.map';

    private const CATALOGUE_KEY = 'icons.catalogue';

    /** @var array<string, string>|null */
    private ?array $memo = null;

    /** SVG body for one icon, or the fallback glyph when the name is unknown. */
    public function body(string $name): string
    {
        $map = $this->map();

        return $map[$name] ?? $map['circle'] ?? '';
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->map());
    }

    /** @return array<string, string> */
    public function map(): array
    {
        // Memoised per request on top of the cache: a page with thirty icons
        // should touch the cache once, not thirty times.
        return $this->memo ??= Cache::rememberForever(self::CACHE_KEY, function () {
            $rows = $this->rows()?->pluck('body', 'name')->all();

            return $rows ?: config('icons', []);
        });
    }

    /**
     * Icons grouped for the picker.
     *
     * @return Collection<string, Collection<int, array{name: string, label: string}>>
     */
    public function grouped(): Collection
    {
        return Cache::rememberForever(self::CATALOGUE_KEY, function () {
            $icons = $this->rows();

            if ($icons === null || $icons->isEmpty()) {
                // Fallback: derive a flat list straight from the config so the
                // picker is never empty on a fresh install.
                return collect(config('icons', []))
                    ->keys()
                    ->map(fn (string $name) => ['name' => $name, 'label' => str($name)->headline()->value()])
                    ->groupBy(fn () => 'Umum');
            }

            return $icons
                ->map(fn (Icon $icon) => ['name' => $icon->name, 'label' => $icon->label, 'group' => $icon->group])
                ->groupBy('group')
                ->map(fn (Collection $group) => $group->map(fn (array $i) => [
                    'name' => $i['name'],
                    'label' => $i['label'],
                ])->values());
        });
    }

    /** @return array<int, string> Every selectable icon name. */
    public function names(): array
    {
        return $this->grouped()->flatten(1)->pluck('name')->all();
    }

    public function forget(): void
    {
        $this->memo = null;
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::CATALOGUE_KEY);
    }

    /**
     * Null when the table cannot be read - during the very first migration, or
     * if the database is down. Callers fall back to the config.
     *
     * @return Collection<int, Icon>|null
     */
    private function rows(): ?Collection
    {
        try {
            return Icon::query()->active()->ordered()->get();
        } catch (\Throwable) {
            return null;
        }
    }
}


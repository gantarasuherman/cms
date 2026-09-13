<?php

namespace App\Services\Analytics;

use App\Models\PageView;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Read-side aggregation over the page_views table.
 *
 * Every figure is computed from the rows rather than kept in a counter, so a
 * prune or a correction is reflected immediately. Results are cached briefly —
 * a dashboard does not need to be real-time, and this keeps repeated loads off
 * the table.
 */
class VisitorStatistics
{
    private const CACHE_TTL = 300;

    private const CACHE_PREFIX = 'analytics.';

    public function enabled(): bool
    {
        return (bool) config('analytics.enabled', true);
    }

    /**
     * Headline figures with their change against the preceding, equal-length
     * period — a number without a comparison is hard to act on.
     *
     * @return array<string, array{value: int, previous: int, delta: ?float}>
     */
    public function summary(): array
    {
        return $this->remember('summary', function () {
            $today = Carbon::today();

            return [
                'visitors_today' => $this->compare(
                    $this->uniqueVisitors($today, $today),
                    $this->uniqueVisitors($today->copy()->subDay(), $today->copy()->subDay()),
                ),
                'views_today' => $this->compare(
                    $this->views($today, $today),
                    $this->views($today->copy()->subDay(), $today->copy()->subDay()),
                ),
                'visitors_7d' => $this->compare(
                    $this->uniqueVisitors($today->copy()->subDays(6), $today),
                    $this->uniqueVisitors($today->copy()->subDays(13), $today->copy()->subDays(7)),
                ),
                'views_30d' => $this->compare(
                    $this->views($today->copy()->subDays(29), $today),
                    $this->views($today->copy()->subDays(59), $today->copy()->subDays(30)),
                ),
            ];
        });
    }

    /**
     * One entry per day for the last $days, including days with no traffic —
     * a gap in a time series must read as zero, not as a missing bar.
     *
     * @return Collection<int, array{date: Carbon, label: string, visitors: int, views: int}>
     */
    public function daily(int $days = 14): Collection
    {
        return $this->remember("daily.$days", function () use ($days) {
            $start = Carbon::today()->subDays($days - 1);

            $rows = PageView::query()
                ->where('viewed_on', '>=', $start->copy()->startOfDay())
                ->groupBy('viewed_on')
                ->select('viewed_on')
                ->selectRaw('COUNT(*) as views')
                ->selectRaw('COUNT(DISTINCT visitor_hash) as visitors')
                ->get()
                ->keyBy(fn (PageView $row) => $row->viewed_on->toDateString());

            return collect(range(0, $days - 1))->map(function (int $offset) use ($start, $rows) {
                $date = $start->copy()->addDays($offset);
                $row = $rows->get($date->toDateString());

                return [
                    'date' => $date,
                    'label' => $date->translatedFormat('d M'),
                    'visitors' => (int) ($row->visitors ?? 0),
                    'views' => (int) ($row->views ?? 0),
                ];
            });
        });
    }

    /** @return Collection<int, array{path: string, label: string, views: int}> */
    public function topPages(int $limit = 6, int $days = 30): Collection
    {
        return $this->remember("top-pages.$limit.$days", fn () => PageView::query()
            ->where('viewed_on', '>=', Carbon::today()->subDays($days - 1)->startOfDay())
            ->groupBy('path')
            ->select('path')
            ->selectRaw('COUNT(*) as views')
            ->orderByDesc('views')
            ->limit($limit)
            ->get()
            ->map(fn (PageView $row) => [
                'path' => $row->path,
                'label' => $row->path === '' ? '/' : '/'.$row->path,
                'views' => (int) $row->views,
            ]));
    }

    /** @return Collection<int, array{host: string, views: int}> */
    public function topReferrers(int $limit = 5, int $days = 30): Collection
    {
        return $this->remember("referrers.$limit.$days", fn () => PageView::query()
            ->whereNotNull('referrer_host')
            ->where('viewed_on', '>=', Carbon::today()->subDays($days - 1)->startOfDay())
            ->groupBy('referrer_host')
            ->select('referrer_host')
            ->selectRaw('COUNT(*) as views')
            ->orderByDesc('views')
            ->limit($limit)
            ->get()
            ->map(fn (PageView $row) => [
                'host' => $row->referrer_host,
                'views' => (int) $row->views,
            ]));
    }

    public function forget(): void
    {
        foreach (['summary', 'daily.7', 'daily.14', 'daily.30'] as $key) {
            Cache::forget(self::CACHE_PREFIX.$key);
        }
    }

    private function views(Carbon $from, Carbon $to): int
    {
        return $this->inRange($from, $to)->count();
    }

    private function uniqueVisitors(Carbon $from, Carbon $to): int
    {
        return (int) $this->inRange($from, $to)->distinct()->count(DB::raw('visitor_hash'));
    }

    /**
     * Bounds the query by a full-day datetime range rather than by bare date
     * strings.
     *
     * MySQL stores a DATE column as `2026-09-12`, but SQLite keeps whatever
     * Eloquent serialises — `2026-09-12 00:00:00` — so comparing against
     * `'2026-09-12'` silently excludes every row there. A range from the start
     * of the first day to the end of the last is correct on both, and still
     * resolves to an index range scan.
     */
    private function inRange(Carbon $from, Carbon $to): \Illuminate\Database\Eloquent\Builder
    {
        return PageView::whereBetween('viewed_on', [
            $from->copy()->startOfDay(),
            $to->copy()->endOfDay(),
        ]);
    }

    /**
     * @return array{value: int, previous: int, delta: ?float}
     */
    private function compare(int $value, int $previous): array
    {
        return [
            'value' => $value,
            'previous' => $previous,
            // Growth from zero has no percentage; the view shows the raw
            // numbers instead of inventing "+100%".
            'delta' => $previous > 0 ? round((($value - $previous) / $previous) * 100, 1) : null,
        ];
    }

    private function remember(string $key, \Closure $callback): mixed
    {
        return Cache::remember(self::CACHE_PREFIX.$key, self::CACHE_TTL, $callback);
    }
}


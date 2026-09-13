<?php

namespace App\Services\Complaints;

use App\Models\Complaint;
use Illuminate\Support\Collection;

/**
 * One place, and every complaint filed about it.
 */
final class LocationCluster
{
    /** @param Collection<int, Complaint> $complaints */
    public function __construct(public readonly Collection $complaints)
    {
    }

    public function count(): int
    {
        return $this->complaints->count();
    }

    /** @return array{0: float, 1: float} The middle of the reported points. */
    public function centre(): array
    {
        return [
            (float) $this->complaints->avg(fn (Complaint $c) => (float) $c->latitude),
            (float) $this->complaints->avg(fn (Complaint $c) => (float) $c->longitude),
        ];
    }

    /**
     * What to call this place.
     *
     * The address a reporter wrote, when they wrote one — the most frequently
     * repeated wins, because the wording people agree on is usually the real
     * landmark. Coordinates only when nobody named it, which is honest about
     * the fact that nothing here knows the street.
     */
    public function label(): string
    {
        $addresses = $this->complaints
            ->map(fn (Complaint $c) => trim((string) $c->address))
            ->filter()
            ->countBy()
            ->sortDesc();

        if ($addresses->isNotEmpty()) {
            return (string) $addresses->keys()->first();
        }

        [$latitude, $longitude] = $this->centre();

        return sprintf('%.5f, %.5f', $latitude, $longitude);
    }

    /**
     * The categories reported here, most reported first.
     *
     * This is what turns a dot into a finding: "3 laporan jalan rusak" rather
     * than "3 laporan".
     *
     * @return Collection<string, int>
     */
    public function categories(): Collection
    {
        return $this->complaints
            ->map(fn (Complaint $c) => $c->category?->name ?: 'Tanpa kategori')
            ->countBy()
            ->sortDesc();
    }

    /** A one-line reading of the cluster, for a heading or a table cell. */
    public function summary(): string
    {
        return $this->categories()
            ->map(fn (int $count, string $name) => $count.' '.$name)
            ->join(', ');
    }

    /** @return Collection<string, int> */
    public function statuses(): Collection
    {
        return $this->complaints->countBy(fn (Complaint $c) => $c->statusLabel())->sortDesc();
    }

    /** Complaints here that nobody has finished yet — the reason to act. */
    public function open(): int
    {
        return $this->complaints->reject(fn (Complaint $c) => $c->status === 'selesai')->count();
    }

    public function latest(): ?Complaint
    {
        return $this->complaints->sortByDesc('created_at')->first();
    }
}

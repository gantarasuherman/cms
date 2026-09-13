<?php

namespace App\Services\Complaints;

use App\Models\Complaint;
use Illuminate\Support\Collection;

/**
 * Groups complaints that are about the same place.
 *
 * "Three road-damage reports at junction A" is a different fact from three
 * reports scattered across a district, and it is the one worth acting on. The
 * grouping is by distance on the ground, not by the address text: two people
 * describing the same pothole write two different addresses, and one of them
 * usually writes nothing at all.
 *
 * Single-link clustering, on purpose: a report joins a group when it is within
 * the radius of *any* report already in it. That lets a damaged stretch of
 * road come out as one finding rather than as four, which is how the people
 * reading this screen think about it.
 */
class LocationClusters
{
    /** Metres. About a city block: close enough to be "the same spot". */
    public const DEFAULT_RADIUS = 250;

    /**
     * @param  Collection<int, Complaint>  $complaints
     * @return Collection<int, LocationCluster>
     */
    public function build(Collection $complaints, int $radius = self::DEFAULT_RADIUS): Collection
    {
        $located = $complaints->filter(fn (Complaint $c) => $c->hasLocation())->values();

        /** @var array<int, array<int, Complaint>> $groups */
        $groups = [];

        foreach ($located as $complaint) {
            $joined = null;

            foreach ($groups as $index => $group) {
                foreach ($group as $member) {
                    if ($this->metresBetween($complaint, $member) > $radius) {
                        continue;
                    }

                    if ($joined === null) {
                        $groups[$index][] = $complaint;
                        $joined = $index;
                    } else {
                        // The report bridges two groups that were separate
                        // only because nothing had linked them yet. Merging
                        // keeps one stretch of road from reading as two.
                        $groups[$joined] = array_merge($groups[$joined], $group);
                        unset($groups[$index]);
                    }

                    break;
                }
            }

            if ($joined === null) {
                $groups[] = [$complaint];
            }
        }

        return collect($groups)
            ->map(fn (array $group) => new LocationCluster(collect($group)))
            ->sortByDesc(fn (LocationCluster $cluster) => $cluster->count())
            ->values();
    }

    /**
     * Haversine. Good to a metre at city scale, which is well past what the
     * coordinates a phone reports are worth.
     */
    private function metresBetween(Complaint $a, Complaint $b): float
    {
        $earth = 6_371_000;

        $lat1 = deg2rad((float) $a->latitude);
        $lat2 = deg2rad((float) $b->latitude);
        $dLat = $lat2 - $lat1;
        $dLng = deg2rad((float) $b->longitude - (float) $a->longitude);

        $h = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dLng / 2) ** 2;

        return 2 * $earth * asin(min(1.0, sqrt($h)));
    }
}

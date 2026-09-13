/**
 * The complaint analysis map.
 *
 * Leaflet, bundled from node_modules rather than a CDN, and pointed at this
 * application's own tile route rather than at OpenStreetMap directly. The map
 * pans and zooms like any other, and the operator's browser still never speaks
 * to a map provider: the server fetches each tile once, caches it, and serves
 * it from disk.
 *
 * The findings come from the server too. Clustering by distance is an analysis
 * — "three road-damage reports at this junction" — not a rendering trick, so it
 * is computed once in PHP and drawn here rather than recomputed per zoom level
 * where the same data would tell a different story at every scale.
 */
import L from 'leaflet';

/** Rank, not count: the busiest spot stays red however many reports it has. */
const RANK_COLOURS = ['#dc2626', '#ea580c', '#ca8a04', '#2563eb'];

function discIcon(count, rank) {
    const size = Math.min(58, 30 + count * 5);
    const colour = RANK_COLOURS[Math.min(rank, RANK_COLOURS.length - 1)];

    return L.divIcon({
        className: 'complaint-pin',
        html: `<span style="--pin: ${colour}; width:${size}px; height:${size}px">${count}</span>`,
        iconSize: [size, size],
        iconAnchor: [size / 2, size / 2],
        popupAnchor: [0, -size / 2],
    });
}

function popupFor(cluster) {
    const rows = cluster.complaints
        .map(
            (c) =>
                `<li><a href="${c.url}">${c.ticket}</a> <span>${c.category}</span>` +
                `<em>${c.status}</em><time>${c.date}</time></li>`,
        )
        .join('');

    return (
        `<div class="complaint-popup"><strong>${cluster.label}</strong>` +
        `<p>${cluster.summary}</p><ol>${rows}</ol></div>`
    );
}

function init(root) {
    const payload = document.getElementById(root.dataset.complaintMap);

    if (!payload) {
        return;
    }

    const { clusters, tiles, attribution } = JSON.parse(payload.textContent);

    if (clusters.length === 0) {
        return;
    }

    const map = L.map(root, { scrollWheelZoom: false });

    L.tileLayer(tiles, { maxZoom: 19, attribution }).addTo(map);

    // Scroll-wheel zoom off until the map is deliberately focused: a map that
    // swallows the page scroll traps someone halfway down a long screen.
    map.on('focus', () => map.scrollWheelZoom.enable());
    map.on('blur', () => map.scrollWheelZoom.disable());

    const bounds = [];

    clusters.forEach((cluster, rank) => {
        bounds.push([cluster.lat, cluster.lng]);

        L.marker([cluster.lat, cluster.lng], {
            icon: discIcon(cluster.count, rank),
            // Read out as a sentence rather than as a bare number, so the
            // marker means something without the popup being opened.
            alt: `${cluster.count} pengaduan di ${cluster.label}: ${cluster.summary}`,
            keyboard: true,
        })
            .addTo(map)
            .bindPopup(popupFor(cluster));
    });

    map.fitBounds(bounds, { padding: [56, 56], maxZoom: 16 });
}

export function initComplaintMaps(scope = document) {
    scope.querySelectorAll('[data-complaint-map]').forEach(init);
}

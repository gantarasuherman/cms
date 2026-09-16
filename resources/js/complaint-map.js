/**
 * The complaint analysis map.
 *
 * Leaflet, bundled from node_modules rather than a CDN, and pointed at this
 * application's own tile route rather than at OpenStreetMap directly. The map
 * pans and zooms like any other, and the operator's browser still never speaks
 * to a map provider: the server fetches each tile once, caches it, and serves
 * it from disk.
 *
 * Satu penanda per pengaduan, dikelompokkan oleh Leaflet.
 *
 * Pengelompokannya dikerjakan di peramban, bukan di server, karena ia harus
 * mengikuti tingkat perbesaran: dua laporan yang bertumpuk pada pandangan
 * seluruh kabupaten adalah dua titik berbeda begitu peta didekatkan. Kelompok
 * yang sudah jadi dari server tidak dapat membuka kembali.
 *
 * Warna menandai jenis pengaduan — tetapi tidak pernah sendirian. Setiap pin
 * juga membawa ikon jenisnya dan popup menyebut namanya, sebab warna saja
 * tidak dapat diandalkan membedakan lebih dari empat jenis, bahkan bagi mata
 * yang membedakan warna sepenuhnya.
 */
import L from 'leaflet';
import 'leaflet.markercluster';

function escapeHtml(value) {
    return String(value ?? '').replace(
        /[&<>"']/g,
        (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c],
    );
}

/**
 * Pin satu pengaduan: cakram berwarna jenisnya, dengan ikon jenis di dalamnya.
 *
 * Cincin putih di tepinya bukan hiasan — sebuah cakram berwarna di atas citra
 * peta yang beragam kehilangan batasnya, dan dua pin bersebelahan melebur jadi
 * satu bentuk tanpa cincin itu.
 */
function pinIcon(point) {
    const glyph = point.icon
        ? `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${point.icon}</svg>`
        : '';

    return L.divIcon({
        className: 'complaint-pin',
        html: `<span class="complaint-pin__dot" style="--pin: ${point.color}">${glyph}</span>`,
        iconSize: [28, 28],
        iconAnchor: [14, 14],
        popupAnchor: [0, -14],
    });
}

/** Lingkaran kelompok: jumlah di dalamnya, membesar mengikuti isinya. */
function clusterIcon(cluster) {
    const count = cluster.getChildCount();
    const size = count < 10 ? 34 : count < 100 ? 42 : 50;

    return L.divIcon({
        className: 'complaint-cluster',
        html: `<span class="complaint-cluster__dot" style="width:${size}px;height:${size}px">${count}</span>`,
        iconSize: [size, size],
    });
}

function popupFor(point) {
    /*
     | Fotonya dimuat hanya ketika popup dibuka.
     |
     | Leaflet baru menyusun isi popup saat diklik, jadi `<img>` di sini tidak
     | pernah menjadi permintaan jaringan untuk titik yang tidak dibuka
     | siapa pun. Sebuah peta dengan dua ratus titik yang memuat dua ratus foto
     | di muka akan menghabiskan kuota petugas di lapangan untuk gambar yang
     | tidak dilihatnya.
     |
     | `loading="lazy"` tetap dipasang agar popup yang dibuka lalu segera
     | ditutup tidak menuntaskan unduhannya.
     */
    const photo = point.photo
        ? `<a class="complaint-popup__photo" href="${escapeHtml(point.url)}">
             <img src="${escapeHtml(point.photo)}" alt="Foto pada pengaduan ${escapeHtml(point.ticket)}"
                  loading="lazy" decoding="async">
             ${point.photos > 1 ? `<span>+${point.photos - 1}</span>` : ''}
           </a>`
        : '';

    const description = point.description
        ? `<p class="complaint-popup__desc">${escapeHtml(point.description)}</p>`
        : '';

    return (
        `<div class="complaint-popup">` +
        photo +
        `<p><a href="${escapeHtml(point.url)}">${escapeHtml(point.ticket)}</a></p>` +
        `<p><span>${escapeHtml(point.category)}</span> · <em>${escapeHtml(point.status)}</em></p>` +
        description +
        `<time>${escapeHtml(point.date)}</time>` +
        `</div>`
    );
}

/**
 * Menggambar batas wilayah, dan meredupkan segala yang di luarnya.
 *
 * Peredupannya satu poligon raksasa seukuran dunia dengan lubang berbentuk
 * wilayahnya — cara baku Leaflet untuk topeng, karena aturan pengisian
 * evenodd membuat bagian dalam lubang tidak ikut terwarnai.
 *
 * Diredupkan, bukan dipotong. Sebuah jalan tidak berhenti di batas kabupaten:
 * petugas perlu melihat ke mana jalan itu menuju, sementara yang di luar
 * wilayah jelas bukan tanggung jawabnya.
 */
async function drawBoundary(map, url, padding) {
    let feature;

    try {
        const response = await fetch(url, { headers: { Accept: 'application/geo+json, application/json' } });

        if (!response.ok) {
            return null;
        }

        feature = await response.json();
    } catch {
        // Batas yang gagal dimuat meninggalkan peta biasa, bukan peta kosong.
        return null;
    }

    const outline = L.geoJSON(feature, {
        style: { color: '#0f172a', weight: 2, fill: false, dashArray: '4 3' },
        interactive: false,
    }).addTo(map);

    const bounds = outline.getBounds();

    // Topeng: dunia sebagai cincin luar, wilayahnya sebagai lubang.
    const rings = [[[-90, -180], [-90, 180], [90, 180], [90, -180]]];

    L.geoJSON(feature, {
        interactive: false,
        onEachFeature: (_, layer) => {
            const shape = layer.getLatLngs();

            (Array.isArray(shape[0][0]) ? shape : [shape]).forEach((polygon) => {
                rings.push(polygon[0] ?? polygon);
            });
        },
    });

    L.polygon(rings, {
        color: 'transparent',
        fillColor: '#0f172a',
        fillOpacity: 0.35,
        fillRule: 'evenodd',
        interactive: false,
    }).addTo(map);

    // Geseran dikurung pada wilayahnya, dengan sedikit ruang di tepi: titik
    // pengaduan yang persis di perbatasan tidak boleh terhimpit ke pinggir
    // layar tanpa ruang di sekelilingnya.
    map.setMaxBounds(bounds.pad(padding ?? 0.05));

    return bounds;
}

function init(root) {
    const payload = document.getElementById(root.dataset.complaintMap);

    if (!payload) {
        return;
    }

    const { points, tiles, attribution, boundary, boundaryPadding } = JSON.parse(payload.textContent);

    if (!points || points.length === 0) {
        return;
    }

    const map = L.map(root, { scrollWheelZoom: false });

    L.tileLayer(tiles, { maxZoom: 19, attribution }).addTo(map);

    // Scroll-wheel zoom off until the map is deliberately focused: a map that
    // swallows the page scroll traps someone halfway down a long screen.
    map.on('focus', () => map.scrollWheelZoom.enable());
    map.on('blur', () => map.scrollWheelZoom.disable());

    const group = L.markerClusterGroup({
        iconCreateFunction: clusterIcon,
        showCoverageOnHover: false,
        // Laporan pada koordinat yang persis sama tetap dapat dibuka
        // satu per satu, bukan menumpuk jadi satu pin yang menyembunyikan
        // sisanya selamanya.
        spiderfyOnMaxZoom: true,
        maxClusterRadius: 45,
    });

    const bounds = [];

    points.forEach((point) => {
        bounds.push([point.lat, point.lng]);

        L.marker([point.lat, point.lng], {
            icon: pinIcon(point),
            // Dibacakan sebagai kalimat, bukan sekadar titik berwarna: jenisnya
            // harus sampai tanpa bergantung pada warna maupun popup.
            alt: `${point.category}, ${point.status}, tiket ${point.ticket}`,
            keyboard: true,
        })
            .bindPopup(popupFor(point))
            .addTo(group);
    });

    group.addTo(map);

    map.fitBounds(bounds, { padding: [56, 56], maxZoom: 16 });

    // Batasnya digambar setelah peta punya pandangan, dan tidak ditunggu:
    // titik-titiknya sudah terbaca sementara berkas batas masih dalam
    // perjalanan.
    if (boundary) {
        drawBoundary(map, boundary, boundaryPadding);
    }
}

export function initComplaintMaps(scope = document) {
    scope.querySelectorAll('[data-complaint-map]').forEach(init);
}

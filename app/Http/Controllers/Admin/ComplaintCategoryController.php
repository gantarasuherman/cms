<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ComplaintCategory;
use App\Services\Audit\AuditLogger;
use App\Services\Cache\PublicCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Jenis pengaduan, dan apa yang harus dibawa sebelum satu laporan diterima.
 *
 * Syarat buktinya baris data, bukan aturan di dalam kode: jalan berlubang
 * tidak berarti apa-apa tanpa foto dan titik lokasi, sementara memaksa orang
 * memotret sesuatu untuk mengadukan pelayanan yang lambat hanya membuat
 * laporannya tidak jadi dikirim. Mesin percakapan membaca kolom ini langsung,
 * jadi menambah kategori atau mengubah syaratnya tidak perlu menyentuh alur
 * percakapan sama sekali.
 */
class ComplaintCategoryController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly PublicCache $cache,
    ) {
    }

    public function index(): View
    {
        $this->authorize('viewAny', ComplaintCategory::class);

        return view('admin.complaints.categories.index', [
            'categories' => ComplaintCategory::withCount(['complaints', 'recipients'])
                ->orderBy('sort_order')
                ->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', ComplaintCategory::class);

        return $this->form(new ComplaintCategory([
            'is_active' => true,
            'requires_photo' => true,
            'requires_location' => true,
            'sort_order' => (int) ComplaintCategory::max('sort_order') + 10,
        ]));
    }

    public function edit(ComplaintCategory $category): View
    {
        $this->authorize('update', $category);

        return $this->form($category);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', ComplaintCategory::class);

        $data = $this->validated($request);
        $category = ComplaintCategory::create($data + ['slug' => Str::slug($data['name'])]);

        $this->audit->recordModel('create', 'complaint_category', $category);
        $this->cache->flushAll();

        return redirect()->route('admin.complaint-categories.index')
            ->with('success', 'Jenis pengaduan ditambahkan.');
    }

    public function update(Request $request, ComplaintCategory $category): RedirectResponse
    {
        $this->authorize('update', $category);

        // Slug sengaja tidak ikut berubah saat namanya diganti. Ia dirujuk
        // dengan nama pada node alur percakapan (`category_slug`), dan
        // mengganti slug karena memperbaiki ejaan nama akan memutus rujukan
        // itu tanpa ada yang terlihat salah di layar mana pun.
        $original = $category->getOriginal();
        $category->update($this->validated($request, $category));

        $this->audit->recordModel('update', 'complaint_category', $category, $original);
        $this->cache->flushAll();

        return redirect()->route('admin.complaint-categories.index')
            ->with('success', 'Jenis pengaduan diperbarui.');
    }

    public function destroy(ComplaintCategory $category): RedirectResponse
    {
        $this->authorize('delete', $category);

        // Menghapusnya akan memutus pengaduan dari jenisnya, dan laporan tanpa
        // jenis tidak dapat disalurkan ke petugas mana pun. Menonaktifkan
        // menyembunyikannya dari menu tanpa merusak yang sudah tercatat.
        if ($category->complaints()->exists()) {
            return back()->with('warning',
                'Masih dipakai '.$category->complaints()->count().' pengaduan. '
                .'Nonaktifkan saja agar tidak muncul lagi di menu chatbot.');
        }

        $category->recipients()->detach();
        $category->delete();

        $this->audit->record('delete', 'complaint_category', $category->getKey());
        $this->cache->flushAll();

        return redirect()->route('admin.complaint-categories.index')
            ->with('success', 'Jenis pengaduan dihapus.');
    }

    private function form(ComplaintCategory $category): View
    {
        return view('admin.complaints.categories.form', ['category' => $category]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?ComplaintCategory $category = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'contact_name' => ['nullable', 'string', 'max:120'],
            // Longgar dengan sengaja: nomor ditulis orang dengan spasi, tanda
            // hubung, awalan 0 atau +62. Menolaknya karena format hanya membuat
            // kolom ini dibiarkan kosong, dan kosong berarti warga tidak diberi
            // nomor sama sekali. Yang dipakai untuk tautan wa.me hanya angkanya.
            'contact_phone' => ['nullable', 'string', 'max:32', 'regex:/^[0-9+\-\s()]+$/'],
            'icon' => ['nullable', 'string', 'max:64', Rule::exists('icons', 'name')],
            // Kosong berarti "pakai bawaan menurut urutan", bukan hitam.
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'requires_photo' => ['nullable', 'boolean'],
            'requires_location' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]) + [
            'requires_photo' => $request->boolean('requires_photo'),
            'requires_location' => $request->boolean('requires_location'),
            'is_active' => $request->boolean('is_active'),
        ];
    }
}

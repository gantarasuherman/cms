<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DispositionTarget;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Instansi tujuan penerusan pengaduan.
 *
 * Daftarnya data, bukan daftar di dalam kode: instansi bertambah dan berkurang,
 * nomornya berganti, dan orang yang tahu hal itu adalah operator — bukan orang
 * yang dapat menyunting berkas di server.
 */
class DispositionTargetController extends Controller
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function index(): View
    {
        $this->authorize('viewAny', DispositionTarget::class);

        return view('admin.complaints.dispositions.index', [
            'targets' => DispositionTarget::withCount('dispositions')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', DispositionTarget::class);

        return $this->form(new DispositionTarget([
            'is_active' => true,
            'sort_order' => (int) DispositionTarget::max('sort_order') + 10,
        ]));
    }

    public function edit(DispositionTarget $target): View
    {
        $this->authorize('update', $target);

        return $this->form($target);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', DispositionTarget::class);

        $data = $this->validated($request);
        $target = DispositionTarget::create($data + ['slug' => Str::slug($data['name'])]);

        $this->audit->recordModel('create', 'disposition_target', $target);

        return redirect()->route('admin.dispositions.index')
            ->with('success', 'Tujuan penerusan ditambahkan.');
    }

    public function update(Request $request, DispositionTarget $target): RedirectResponse
    {
        $this->authorize('update', $target);

        $original = $target->getOriginal();
        $target->update($this->validated($request));

        $this->audit->recordModel('update', 'disposition_target', $target, $original);

        return redirect()->route('admin.dispositions.index')
            ->with('success', 'Tujuan penerusan diperbarui.');
    }

    public function destroy(DispositionTarget $target): RedirectResponse
    {
        $this->authorize('delete', $target);

        // Disposisi yang sudah terjadi menyimpan salinan nama dan nomornya,
        // jadi menghapus tujuan tidak merusak riwayat. Tetap diperingatkan
        // supaya penghapusan tidak terjadi tanpa disadari.
        $used = $target->dispositions()->count();

        $target->delete();
        $this->audit->record('delete', 'disposition_target', $target->getKey());

        return redirect()->route('admin.dispositions.index')->with(
            'success',
            $used > 0
                ? 'Tujuan dihapus. '.$used.' penerusan yang sudah terjadi tetap tercatat pada riwayat pengaduannya.'
                : 'Tujuan penerusan dihapus.',
        );
    }

    private function form(DispositionTarget $target): View
    {
        return view('admin.complaints.dispositions.form', ['target' => $target]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'contact_person' => ['nullable', 'string', 'max:120'],
            // Format internasional tanpa tanda baca: Cloud API menolak bentuk
            // lain, dan kegagalannya baru terlihat saat penerusan pertama
            // tidak sampai.
            'phone' => ['required', 'string', 'max:32', 'regex:/^[0-9]+$/'],
            'description' => ['nullable', 'string', 'max:255'],
            'reporter_template' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'phone.regex' => 'Isi angka saja, format internasional: 628123456789.',
        ]) + ['is_active' => $request->boolean('is_active')];
    }
}

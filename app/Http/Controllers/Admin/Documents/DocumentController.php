<?php

namespace App\Http\Controllers\Admin\Documents;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DocumentRequest;
use App\Models\Category;
use App\Models\Document;
use App\Services\Documents\DocumentWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class DocumentController extends Controller
{
    public function __construct(private readonly DocumentWriter $writer)
    {
    }

    public function index(): View
    {
        $this->authorize('viewAny', Document::class);

        return view('admin.documents.index', [
            'categories' => Category::ofType(Category::TYPE_DOCUMENT)->ordered()->pluck('name', 'id'),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Document::class);

        $query = Document::query()->with('category')->select('documents.*');

        if ($request->filled('category')) {
            $query->where('category_id', $request->integer('category'));
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->string('status')->toString() === 'active');
        }

        return DataTables::eloquent($query)
            ->addColumn('category_name', fn (Document $d) => $d->category?->name ?? '—')
            ->addColumn('file_info', fn (Document $d) => strtoupper($d->file_extension).' · '.$this->humanSize($d->file_size))
            ->editColumn('is_active', fn (Document $d) => view('components.status-badge', [
                'status' => $d->is_active ? 'active' : 'inactive',
            ])->render())
            ->editColumn('download_count', fn (Document $d) => number_format($d->download_count))
            ->addColumn('actions', fn (Document $d) => view('admin.documents.partials.actions', ['document' => $d])->render())
            ->filterColumn('category_name', fn ($q, $keyword) => $q->whereHas('category', fn ($c) => $c->where('name', 'like', "%{$keyword}%")))
            ->rawColumns(['is_active', 'actions'])
            ->toJson();
    }

    public function create(): View
    {
        $this->authorize('create', Document::class);

        return view('admin.documents.form', $this->formData(new Document(['is_active' => true])));
    }

    public function store(DocumentRequest $request): RedirectResponse
    {
        $this->authorize('create', Document::class);

        $this->writer->create($request->validated(), $request->file('file'));

        return redirect()
            ->route('admin.documents.index')
            ->with('success', 'Dokumen berhasil diunggah.');
    }

    public function edit(Document $document): View
    {
        $this->authorize('update', $document);

        return view('admin.documents.form', $this->formData($document));
    }

    public function update(DocumentRequest $request, Document $document): RedirectResponse
    {
        $this->authorize('update', $document);

        $this->writer->update($document, $request->validated(), $request->file('file'));

        return redirect()
            ->route('admin.documents.index')
            ->with('success', 'Dokumen berhasil diperbarui.');
    }

    public function destroy(Document $document): RedirectResponse
    {
        $this->authorize('delete', $document);

        $this->writer->delete($document);

        return redirect()
            ->route('admin.documents.index')
            ->with('success', 'Dokumen berhasil dihapus.');
    }

    /** @return array<string, mixed> */
    private function formData(Document $document): array
    {
        return [
            'document' => $document,
            'categories' => Category::ofType(Category::TYPE_DOCUMENT)->ordered()->pluck('name', 'id'),
        ];
    }

    private function humanSize(int $bytes): string
    {
        foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
            if ($bytes < 1024) {
                return round($bytes, $unit === 'B' ? 0 : 1).' '.$unit;
            }

            $bytes /= 1024;
        }

        return round($bytes, 1).' TB';
    }
}


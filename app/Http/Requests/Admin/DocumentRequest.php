<?php

namespace App\Http\Requests\Admin;

use App\Models\Category;
use App\Services\Media\MediaService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $document = $this->route('document');

        return [
            'category_id' => ['nullable', Rule::exists('categories', 'id')->where('type', Category::TYPE_DOCUMENT)],
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable', 'string', 'max:255', 'alpha_dash',
                Rule::unique('documents', 'slug')->ignore($document?->getKey())->withoutTrashed(),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'file' => [
                // Required only when creating: editing metadata must not force
                // a re-upload of the same file.
                $document ? 'nullable' : 'required',
                'file',
                'max:51200',
                // Both the extension and the detected MIME type are checked.
                // An allow-list of extensions alone is not enough, and a MIME
                // check alone lets "report.pdf.php" through.
                'mimes:'.implode(',', MediaService::DOCUMENT_EXTENSIONS),
            ],
            'published_at' => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['title' => 'judul', 'file' => 'berkas', 'category_id' => 'kategori'];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'file.mimes' => 'Format berkas harus salah satu dari: '
                .strtoupper(implode(', ', MediaService::DOCUMENT_EXTENSIONS)).'.',
            'file.max' => 'Ukuran berkas maksimal 50 MB.',
        ];
    }
}


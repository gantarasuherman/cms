<?php

namespace App\Http\Requests\Admin;

use App\Services\Media\MediaService;
use Illuminate\Foundation\Http\FormRequest;

class MediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $allowed = [
            ...MediaService::IMAGE_EXTENSIONS,
            ...MediaService::VIDEO_EXTENSIONS,
            ...MediaService::DOCUMENT_EXTENSIONS,
        ];

        return [
            'file' => ['required', 'file', 'max:51200', 'mimes:'.implode(',', $allowed)],
            'alt_text' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['file' => 'berkas', 'alt_text' => 'teks alternatif'];
    }
}


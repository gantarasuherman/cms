<?php

namespace App\Http\Requests\Admin;

use App\Models\Category;
use App\Models\Service;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $service = $this->route('service');

        return [
            'category_id' => ['nullable', Rule::exists('categories', 'id')->where('type', Category::TYPE_SERVICE)],
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable', 'string', 'max:255', 'alpha_dash',
                Rule::unique('services', 'slug')->ignore($service?->getKey())->withoutTrashed(),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'content' => ['nullable', 'string'],
            // Must be an icon that actually exists in the bank: a name the
            // renderer cannot resolve would silently fall back to a blank glyph.
            'icon' => ['nullable', 'string', 'max:64', Rule::exists('icons', 'name')->where('is_active', true)],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_image' => ['nullable', 'boolean'],
            // Free text: "3 hari kerja", "1x24 jam" — deliberately not an enum,
            // because the wording differs per institution.
            'processing_time' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::in(Service::statuses())],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:500'],
            'seo_keywords' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => 'nama layanan',
            'category_id' => 'kategori',
            'processing_time' => 'waktu pelayanan',
        ];
    }
}


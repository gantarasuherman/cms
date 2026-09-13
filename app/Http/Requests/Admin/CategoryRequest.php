<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $type = (string) $this->route('type');
        $category = $this->route('category');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable', 'string', 'max:255', 'alpha_dash',
                // Slugs only need to be unique inside their own taxonomy.
                Rule::unique('categories', 'slug')
                    ->where('type', $type)
                    ->ignore($category?->getKey())
                    ->withoutTrashed(),
            ],
            'parent_id' => [
                'nullable',
                Rule::exists('categories', 'id')->where('type', $type),
                // A category cannot be its own parent.
                Rule::notIn([$category?->getKey()]),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            // Must be an icon that actually exists in the bank: a name the
            // renderer cannot resolve would silently fall back to a blank glyph.
            'icon' => ['nullable', 'string', 'max:64', Rule::exists('icons', 'name')->where('is_active', true)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['parent_id.not_in' => 'Kategori tidak dapat menjadi induk bagi dirinya sendiri.'];
    }
}


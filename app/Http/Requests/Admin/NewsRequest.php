<?php

namespace App\Http\Requests\Admin;

use App\Models\Category;
use App\Models\News;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NewsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route-level authorization already ran; keeping this true avoids a
        // second, divergent copy of the same rule.
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $news = $this->route('news');

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable', 'string', 'max:255', 'alpha_dash',
                Rule::unique('news', 'slug')->ignore($news?->getKey())->withoutTrashed(),
            ],
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'content' => ['nullable', 'string'],
            'featured_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_featured_image' => ['nullable', 'boolean'],
            'status' => ['required', Rule::in(News::statuses())],
            'published_at' => ['nullable', 'date'],
            'is_featured' => ['nullable', 'boolean'],
            'categories' => ['array'],
            'categories.*' => [
                Rule::exists('categories', 'id')->where('type', Category::TYPE_NEWS),
            ],
            'tags' => ['array'],
            'tags.*' => ['string', 'max:50'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:500'],
            'seo_keywords' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'title' => 'judul',
            'content' => 'isi berita',
            'featured_image' => 'gambar utama',
            'categories' => 'kategori',
        ];
    }
}


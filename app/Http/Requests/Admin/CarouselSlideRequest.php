<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CarouselSlideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $slide = $this->route('carousel');

        return [
            'title' => ['nullable', 'string', 'max:150'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:64'],
            // Two to three lines on the hero; a longer text would be clipped
            // rather than read.
            'description' => ['nullable', 'string', 'max:320'],
            'image' => [$slide ? 'nullable' : 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:6144'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'link' => ['nullable', 'string', 'max:255', 'not_regex:/^\s*(javascript|data|vbscript):/i'],
            'button_text' => ['nullable', 'string', 'max:48'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'start_date' => ['nullable', 'date'],
            // A window that ends before it starts would never show the slide,
            // which looks like a bug rather than a choice.
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'image' => 'gambar',
            'alt_text' => 'teks alternatif',
            'start_date' => 'tanggal mulai',
            'end_date' => 'tanggal selesai',
            'description' => 'deskripsi',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'end_date.after_or_equal' => 'Tanggal selesai tidak boleh mendahului tanggal mulai.',
            'link.not_regex' => 'Alamat tautan tidak valid.',
            'image.max' => 'Ukuran gambar maksimal 6 MB.',
        ];
    }
}


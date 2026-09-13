<?php

namespace App\Http\Requests\Admin;

use App\Models\Announcement;
use Illuminate\Foundation\Http\FormRequest;

class AnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $announcement = $this->route('announcement');

        return $announcement instanceof Announcement
            ? $this->user()->can('update', $announcement)
            : $this->user()->can('create', Announcement::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'badge' => ['nullable', 'string', 'max:48'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:1000'],
            'code' => ['nullable', 'string', 'max:64'],
            'button_text' => ['nullable', 'string', 'max:64'],
            'link' => ['nullable', 'string', 'max:255'],
            'ticker_text' => ['nullable', 'string', 'max:160'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'end_date.after_or_equal' => 'Tanggal selesai tidak boleh mendahului tanggal mulai.',
            'ticker_text.max' => 'Teks berjalan dibatasi 160 karakter agar tetap terbaca dalam satu baris.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // A button with no destination is a dead control, and a destination
        // with no button is invisible — neither half is useful alone.
        $this->merge([
            'button_text' => $this->filled('link') ? $this->input('button_text') : null,
            'link' => $this->filled('button_text') ? $this->input('link') : null,
        ]);
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function ($validator) {
            $link = (string) $this->input('link');

            // Same rule the social links follow: a javascript: or data: URL in
            // an href is a scripted payload wearing a link's clothes.
            if ($link !== '' && ! preg_match('#^(https?://|/|mailto:|tel:)#i', $link)) {
                $validator->errors()->add('link', 'Tautan harus diawali http://, https://, /, mailto:, atau tel:.');
            }
        });
    }
}

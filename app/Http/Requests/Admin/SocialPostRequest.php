<?php

namespace App\Http\Requests\Admin;

use App\Models\SocialPost;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SocialPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        $post = $this->route('social_post');

        return $post instanceof SocialPost
            ? $this->user()->can('update', $post)
            : $this->user()->can('create', SocialPost::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'platform' => ['required', 'string', Rule::in(array_keys(SocialPost::PLATFORMS))],
            // Never required: the picture normally arrives from the platform,
            // and an upload is only the fallback for what cannot be fetched.
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:6144'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'account_handle' => ['nullable', 'string', 'max:64', 'regex:/^@?[A-Za-z0-9._]+$/'],
            'caption' => ['nullable', 'string', 'max:2000'],
            // Nullable, not defaulted to zero: an unrecorded count must stay
            // unknown rather than become a claim that nobody liked the post.
            'likes' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'comments' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            // Only http(s): the card is a link out to the original post, and a
            // scheme like javascript: here would be a scripted payload.
            'permalink' => ['required', 'string', 'max:255', 'regex:/^https?:\/\//i'],
            'posted_at' => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],
            // No API reports the blue tick for somebody else's account, so it
            // is an editor's statement rather than a synced figure.
            'is_verified' => ['nullable', 'boolean'],
            'sync_enabled' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'permalink.required' => 'Tautan ke unggahan aslinya wajib diisi.',
            'permalink.regex' => 'Tautan harus diawali http:// atau https://.',
            'account_handle.regex' => 'Nama akun hanya boleh berisi huruf, angka, titik, dan garis bawah.',
        ];
    }
}

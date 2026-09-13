<?php

namespace App\Http\Requests\Admin;

use App\Models\Menu;
use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var class-string<Menu> $model */
        $model = $this->route()->defaults['model'];
        $table = (new $model())->getTable();
        $menu = $this->route('menu');

        return [
            'title' => ['required', 'string', 'max:120'],
            'slug' => [
                'nullable', 'string', 'max:140', 'alpha_dash',
                Rule::unique($table, 'slug')->ignore($menu?->getKey()),
            ],
            // Must be an icon that actually exists in the bank: a name the
            // renderer cannot resolve would silently fall back to a blank glyph.
            'icon' => ['nullable', 'string', 'max:64', Rule::exists('icons', 'name')->where('is_active', true)],
            'route' => ['nullable', 'string', 'max:180'],
            // Relative paths and absolute URLs are both legitimate here, so the
            // check is that it is not a script-bearing scheme.
            'url' => ['nullable', 'string', 'max:255', 'not_regex:/^\s*(javascript|data|vbscript):/i'],
            'parent_id' => [
                'nullable',
                Rule::exists($table, 'id'),
                Rule::notIn([$menu?->getKey()]),
            ],
            'permission' => $table === 'admin_menus'
                ? ['nullable', 'string', Rule::in(Permissions::all())]
                : ['nullable'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['nullable', 'boolean'],
            'target' => ['required', Rule::in(['_self', '_blank'])],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'url.not_regex' => 'Alamat tautan tidak valid.',
            'parent_id.not_in' => 'Menu tidak dapat menjadi induk bagi dirinya sendiri.',
            'permission.in' => 'Hak akses tidak dikenali.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['title' => 'judul', 'parent_id' => 'induk', 'target' => 'target'];
    }
}


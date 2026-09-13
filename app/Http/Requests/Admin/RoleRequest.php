<?php

namespace App\Http\Requests\Admin;

use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $role = $this->route('role');

        return [
            'name' => [
                'required', 'string', 'max:64',
                Rule::unique('roles', 'name')->ignore($role?->getKey()),
                // The seeded superuser role is defined by name; letting another
                // role take that name would hand out the Gate::before bypass.
                Rule::notIn($role?->name === 'Super Admin' ? [] : ['Super Admin']),
            ],
            'permissions' => ['array'],
            // Only abilities the code actually checks may be granted. Without
            // this a crafted form could store permissions that look real in the
            // UI but guard nothing.
            'permissions.*' => [Rule::in(Permissions::all())],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.not_in' => 'Nama "Super Admin" dicadangkan oleh sistem.',
            'permissions.*.in' => 'Ada hak akses yang tidak dikenali.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['name' => 'nama peran', 'permissions' => 'hak akses'];
    }
}


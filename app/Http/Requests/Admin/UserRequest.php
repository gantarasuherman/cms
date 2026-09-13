<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($user?->getKey())->withoutTrashed(),
            ],
            // Optional when editing: leaving it blank keeps the current password.
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::defaults()],
            'is_active' => ['nullable', 'boolean'],
            'roles' => ['array'],
            'roles.*' => [Rule::exists('roles', 'name')],
        ];
    }

    /**
     * Only a Super Admin may hand out the Super Admin role. Without this, any
     * account holding user.update could promote itself past every policy in
     * the system.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $roles = (array) $this->input('roles', []);

            if (in_array('Super Admin', $roles, true) && ! $this->user()?->hasRole('Super Admin')) {
                $validator->errors()->add('roles', 'Hanya Super Admin yang dapat memberikan peran Super Admin.');
            }
        });
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['name' => 'nama', 'email' => 'email', 'password' => 'kata sandi', 'roles' => 'peran'];
    }
}


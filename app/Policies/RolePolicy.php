<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Permissions;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

class RolePolicy extends ModulePolicy
{
    /** Roles this system depends on; renaming or deleting them breaks access control. */
    private const PROTECTED_ROLES = ['Super Admin'];

    /** Abilities that stay denied on a protected role, even for a superuser. */
    public const GUARDED_ABILITIES = ['update', 'delete', 'forceDelete'];

    protected function module(): string
    {
        return 'role';
    }

    public function update(User $user, Model $model): bool
    {
        return ! self::isProtected($model) && parent::update($user, $model);
    }

    public function delete(User $user, Model $model): bool
    {
        return ! self::isProtected($model) && parent::delete($user, $model);
    }

    public static function isProtected(mixed $role): bool
    {
        return $role instanceof Role && in_array($role->name, self::PROTECTED_ROLES, true);
    }
}


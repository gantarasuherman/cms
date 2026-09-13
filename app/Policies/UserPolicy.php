<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Permissions;
use Illuminate\Database\Eloquent\Model;

class UserPolicy extends ModulePolicy
{
    /** Abilities an account may never exercise on itself, superuser included. */
    public const GUARDED_ABILITIES = ['delete', 'forceDelete'];

    protected function module(): string
    {
        return 'user';
    }

    /** An account may never delete itself out from under its own session. */
    public function delete(User $user, Model $model): bool
    {
        return $user->isNot($model) && parent::delete($user, $model);
    }
}


<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Permissions;
use Illuminate\Database\Eloquent\Model;

/**
 * Maps the standard CRUD abilities onto the permission catalogue, so a module
 * policy only has to name its module. Super Admin is handled globally by the
 * Gate::before rule in AppServiceProvider.
 */
abstract class ModulePolicy
{
    abstract protected function module(): string;

    public function viewAny(User $user): bool
    {
        return $user->can($this->ability(Permissions::VIEW));
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can($this->ability(Permissions::VIEW));
    }

    public function create(User $user): bool
    {
        return $user->can($this->ability(Permissions::CREATE));
    }

    public function update(User $user, Model $model): bool
    {
        return $user->can($this->ability(Permissions::UPDATE));
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->can($this->ability(Permissions::DELETE));
    }

    public function restore(User $user, Model $model): bool
    {
        return $user->can($this->ability(Permissions::DELETE));
    }

    public function forceDelete(User $user, Model $model): bool
    {
        return $user->can($this->ability(Permissions::DELETE));
    }

    protected function ability(string $ability): string
    {
        return $this->module().'.'.$ability;
    }
}


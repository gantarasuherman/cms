<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Permissions;
use Illuminate\Database\Eloquent\Model;

class PagePolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'page';
    }

    public function publish(User $user, Model $model): bool
    {
        return $user->can($this->ability(Permissions::PUBLISH));
    }
}


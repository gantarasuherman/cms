<?php

namespace App\Support;

use App\Models\User;
use App\Policies\RolePolicy;
use App\Policies\UserPolicy;

/**
 * Denials that hold for everyone, including Super Admin.
 *
 * The Gate::before rule grants Super Admin every ability, which is what keeps
 * new modules working without re-seeding permissions. But a handful of rules
 * are not about privilege at all — they protect the system from being put into
 * a state it cannot recover from:
 *
 *   - renaming or deleting the "Super Admin" role would break the very rule
 *     that grants the bypass;
 *   - deleting your own account locks you out of the panel you are standing in.
 *
 * Listing them here means the superuser bypass steps aside and the ordinary
 * policy runs, which then denies. The policies stay the single place that says
 * *why* something is denied; this only says where the bypass does not apply.
 */
final class StructuralGuards
{
    /**
     * True when the Super Admin bypass must not short-circuit the policy.
     *
     * @param  array<int, mixed>  $arguments
     */
    public static function applyTo(User $user, string $ability, array $arguments): bool
    {
        $subject = $arguments[0] ?? null;

        if (RolePolicy::isProtected($subject) && in_array($ability, RolePolicy::GUARDED_ABILITIES, true)) {
            return true;
        }

        if ($subject instanceof User && in_array($ability, UserPolicy::GUARDED_ABILITIES, true) && $user->is($subject)) {
            return true;
        }

        return false;
    }
}


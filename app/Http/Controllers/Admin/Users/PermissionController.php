<?php

namespace App\Http\Controllers\Admin\Users;

use App\Http\Controllers\Controller;
use App\Support\Permissions;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Read-only view of the ability catalogue and which roles hold each ability.
 *
 * Permissions are deliberately not editable here: they are the vocabulary the
 * code checks, so inventing one from the UI would create an ability that
 * guards nothing. Roles are where the dynamic part lives.
 */
class PermissionController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Role::class);

        $holders = Permission::query()
            ->with('roles:id,name')
            ->get()
            ->mapWithKeys(fn (Permission $permission) => [
                $permission->name => $permission->roles->pluck('name')->all(),
            ]);

        return view('admin.permissions.index', [
            'modules' => Permissions::modules(),
            'holders' => $holders,
        ]);
    }
}


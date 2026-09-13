<?php

namespace App\Http\Controllers\Admin\Users;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoleRequest;
use App\Services\Audit\AuditLogger;
use App\Support\Permissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;
use Yajra\DataTables\Facades\DataTables;

class RoleController extends Controller
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function index(): View
    {
        $this->authorize('viewAny', Role::class);

        return view('admin.roles.index');
    }

    public function data(): JsonResponse
    {
        $this->authorize('viewAny', Role::class);

        $query = Role::query()->withCount(['permissions', 'users'])->select('roles.*');

        return DataTables::eloquent($query)
            ->addColumn('permission_summary', fn (Role $role) => $role->name === 'Super Admin'
                ? 'Semua hak akses'
                : $role->permissions_count.' hak akses')
            ->addColumn('user_count', fn (Role $role) => (string) $role->users_count)
            ->addColumn('actions', fn (Role $role) => view('admin.roles.partials.actions', ['role' => $role])->render())
            ->rawColumns(['actions'])
            ->toJson();
    }

    public function create(): View
    {
        $this->authorize('create', Role::class);

        return view('admin.roles.form', $this->matrixData(new Role()));
    }

    public function store(RoleRequest $request): RedirectResponse
    {
        $this->authorize('create', Role::class);

        $role = Role::create(['name' => $request->validated('name'), 'guard_name' => 'web']);
        $role->syncPermissions($request->validated('permissions', []));

        $this->audit->record('create', 'role', $role->getKey(), null, [
            'name' => $role->name,
            'permissions' => $request->validated('permissions', []),
        ]);

        return redirect()->route('admin.roles.index')->with('success', 'Peran berhasil dibuat.');
    }

    public function edit(Role $role): View
    {
        $this->authorize('update', $role);

        return view('admin.roles.form', $this->matrixData($role));
    }

    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        $this->authorize('update', $role);

        $before = $role->permissions->pluck('name')->sort()->values()->all();

        $role->update(['name' => $request->validated('name')]);
        $role->syncPermissions($request->validated('permissions', []));

        $this->audit->record('update', 'role', $role->getKey(),
            ['permissions' => $before],
            ['permissions' => $request->validated('permissions', [])],
        );

        return redirect()->route('admin.roles.index')->with('success', 'Peran berhasil diperbarui.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        $this->authorize('delete', $role);

        if ($role->users()->exists()) {
            return back()->with('error', 'Peran masih dipakai pengguna dan tidak dapat dihapus.');
        }

        $role->delete();
        $this->audit->record('delete', 'role', $role->getKey());

        return redirect()->route('admin.roles.index')->with('success', 'Peran berhasil dihapus.');
    }

    /** @return array<string, mixed> */
    private function matrixData(Role $role): array
    {
        return [
            'role' => $role,
            'modules' => Permissions::modules(),
            'abilities' => Permissions::abilityColumns(),
            'granted' => $role->exists ? $role->permissions->pluck('name')->all() : [],
        ];
    }
}


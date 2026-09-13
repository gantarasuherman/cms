<?php

namespace App\Http\Controllers\Admin\Users;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;
use Yajra\DataTables\Facades\DataTables;

class UserController extends Controller
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function index(): View
    {
        $this->authorize('viewAny', User::class);

        return view('admin.users.index', ['roles' => Role::orderBy('name')->pluck('name', 'name')]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $query = User::query()->with('roles:id,name')->select('users.*');

        if ($request->filled('role')) {
            $query->whereHas('roles', fn ($q) => $q->where('name', $request->string('role')));
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->string('status')->toString() === 'active');
        }

        return DataTables::eloquent($query)
            ->addColumn('role_names', fn (User $user) => $user->roles->pluck('name')->join(', ') ?: '—')
            ->editColumn('is_active', fn (User $user) => view('components.status-badge', [
                'status' => $user->is_active ? 'active' : 'inactive',
            ])->render())
            ->editColumn('last_login_at', fn (User $user) => $user->last_login_at?->translatedFormat('d M Y H:i') ?? 'Belum pernah')
            ->addColumn('actions', fn (User $user) => view('admin.users.partials.actions', ['user' => $user])->render())
            ->rawColumns(['is_active', 'actions'])
            ->toJson();
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('admin.users.form', $this->formData(new User(['is_active' => true])));
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $user = User::create($request->safe()->only(['name', 'email', 'password', 'is_active']));
        $user->syncRoles($request->validated('roles', []));

        $this->audit->record('create', 'user', $user->getKey(), null, [
            'email' => $user->email,
            'roles' => $request->validated('roles', []),
        ]);

        return redirect()->route('admin.users.index')->with('success', 'Pengguna berhasil dibuat.');
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        return view('admin.users.form', $this->formData($user));
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $data = $request->safe()->only(['name', 'email', 'is_active']);

        if ($request->filled('password')) {
            $data['password'] = $request->validated('password');
        }

        $before = ['email' => $user->email, 'roles' => $user->roles->pluck('name')->all()];

        $user->update($data);

        // An account must not be able to strip its own roles and lock itself
        // out of the panel it is standing in.
        if ($request->user()->isNot($user)) {
            $user->syncRoles($request->validated('roles', []));
        }

        $this->audit->record('update', 'user', $user->getKey(), $before, [
            'email' => $user->email,
            'roles' => $user->fresh()->roles->pluck('name')->all(),
        ]);

        return redirect()->route('admin.users.index')->with('success', 'Pengguna berhasil diperbarui.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $user->delete();
        $this->audit->record('delete', 'user', $user->getKey());

        return redirect()->route('admin.users.index')->with('success', 'Pengguna berhasil dihapus.');
    }

    /** @return array<string, mixed> */
    private function formData(User $user): array
    {
        return [
            'user' => $user,
            'roles' => Role::orderBy('name')->pluck('name', 'name'),
            'assigned' => $user->exists ? $user->roles->pluck('name')->all() : [],
        ];
    }
}


<?php

namespace Tests\Feature\Admin;

use App\Models\News;
use App\Models\User;
use App\Support\Permissions;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolePermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super Admin');
    }

    public function test_the_matrix_renders_every_module_and_ability(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.roles.create'))->assertOk();

        foreach (Permissions::modules() as $module) {
            $response->assertSee($module['label']);
        }

        foreach (Permissions::abilityColumns() as $ability) {
            $response->assertSee(Permissions::abilityLabel($ability));
        }
    }

    public function test_a_role_is_created_with_the_checked_permissions(): void
    {
        $this->actingAs($this->admin)->post(route('admin.roles.store'), [
            'name' => 'Redaktur',
            'permissions' => ['news.view', 'news.create', 'news.publish'],
        ])->assertRedirect(route('admin.roles.index'));

        $role = Role::findByName('Redaktur');

        $this->assertEqualsCanonicalizing(
            ['news.view', 'news.create', 'news.publish'],
            $role->permissions->pluck('name')->all(),
        );
    }

    public function test_an_unknown_permission_is_rejected(): void
    {
        $this->actingAs($this->admin)->post(route('admin.roles.store'), [
            'name' => 'Penyusup',
            'permissions' => ['news.view', 'news.hapus_semuanya'],
        ])->assertSessionHasErrors('permissions.1');

        $this->assertNull(Role::where('name', 'Penyusup')->first());
    }

    public function test_granting_a_permission_actually_changes_what_a_user_may_do(): void
    {
        $role = Role::create(['name' => 'Kontributor', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole($role);

        // Nothing granted yet.
        $this->actingAs($user)->get(route('admin.news.create'))->assertForbidden();

        $this->actingAs($this->admin)->put(route('admin.roles.update', $role), [
            'name' => 'Kontributor',
            'permissions' => ['news.view', 'news.create'],
        ])->assertRedirect();

        // The UI checkbox is not the mechanism — the policy is.
        $this->actingAs($user->fresh())->get(route('admin.news.create'))->assertOk();
    }

    public function test_revoking_a_permission_takes_effect(): void
    {
        $role = Role::create(['name' => 'Sementara', 'guard_name' => 'web']);
        $role->syncPermissions(['news.view', 'news.create']);

        $user = User::factory()->create();
        $user->assignRole($role);

        $this->actingAs($user)->get(route('admin.news.create'))->assertOk();

        $this->actingAs($this->admin)->put(route('admin.roles.update', $role), [
            'name' => 'Sementara',
            'permissions' => ['news.view'],
        ])->assertRedirect();

        $this->actingAs($user->fresh())->get(route('admin.news.create'))->assertForbidden();
    }

    public function test_the_super_admin_role_cannot_be_edited_or_deleted(): void
    {
        $role = Role::findByName('Super Admin');

        $this->actingAs($this->admin)->get(route('admin.roles.edit', $role))->assertForbidden();
        $this->actingAs($this->admin)->delete(route('admin.roles.destroy', $role))->assertForbidden();

        $this->assertNotNull(Role::where('name', 'Super Admin')->first());
    }

    public function test_another_role_cannot_take_the_super_admin_name(): void
    {
        $this->actingAs($this->admin)->post(route('admin.roles.store'), [
            'name' => 'Super Admin',
        ])->assertSessionHasErrors('name');
    }

    public function test_a_role_still_in_use_is_not_deleted(): void
    {
        $role = Role::create(['name' => 'Dipakai', 'guard_name' => 'web']);
        User::factory()->create()->assignRole($role);

        $this->actingAs($this->admin)
            ->delete(route('admin.roles.destroy', $role))
            ->assertRedirect();

        $this->assertNotNull(Role::where('name', 'Dipakai')->first());
    }

    public function test_permission_changes_are_audited(): void
    {
        $role = Role::create(['name' => 'Audit', 'guard_name' => 'web']);

        $this->actingAs($this->admin)->put(route('admin.roles.update', $role), [
            'name' => 'Audit',
            'permissions' => ['news.view'],
        ]);

        $this->assertDatabaseHas('audit_logs', ['module' => 'role', 'action' => 'update']);
    }

    public function test_a_viewer_cannot_open_the_matrix(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('news.view');

        $this->actingAs($viewer)->get(route('admin.roles.index'))->assertForbidden();
        $this->actingAs($viewer)->get(route('admin.roles.create'))->assertForbidden();
    }
}

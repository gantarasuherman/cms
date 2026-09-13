<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
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

    public function test_a_user_can_be_created_with_roles(): void
    {
        $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'name' => 'Rina Editor',
            'email' => 'rina@example.test',
            'password' => 'kata-sandi-rahasia',
            'password_confirmation' => 'kata-sandi-rahasia',
            'is_active' => 1,
            'roles' => ['Editor'],
        ])->assertRedirect(route('admin.users.index'));

        $user = User::firstWhere('email', 'rina@example.test');

        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('Editor'));
        $this->assertTrue(Hash::check('kata-sandi-rahasia', $user->password));
    }

    public function test_editing_without_a_password_keeps_the_current_one(): void
    {
        $user = User::factory()->create(['password' => Hash::make('sandi-lama')]);

        $this->actingAs($this->admin)->put(route('admin.users.update', $user), [
            'name' => 'Nama Baru',
            'email' => $user->email,
            'roles' => ['Viewer'],
        ])->assertRedirect();

        $user->refresh();

        $this->assertSame('Nama Baru', $user->name);
        $this->assertTrue(Hash::check('sandi-lama', $user->password));
    }

    public function test_a_non_super_admin_cannot_grant_the_super_admin_role(): void
    {
        $manager = User::factory()->create();
        $manager->givePermissionTo(['user.view', 'user.create', 'user.update']);

        $this->actingAs($manager)->post(route('admin.users.store'), [
            'name' => 'Calon Penyusup',
            'email' => 'penyusup@example.test',
            'password' => 'kata-sandi-rahasia',
            'password_confirmation' => 'kata-sandi-rahasia',
            'roles' => ['Super Admin'],
        ])->assertSessionHasErrors('roles');

        $this->assertNull(User::firstWhere('email', 'penyusup@example.test'));
    }

    public function test_a_user_cannot_delete_their_own_account(): void
    {
        $this->actingAs($this->admin)
            ->delete(route('admin.users.destroy', $this->admin))
            ->assertForbidden();

        $this->assertNotSoftDeleted($this->admin);
    }

    public function test_a_user_cannot_change_their_own_roles(): void
    {
        $this->actingAs($this->admin)->put(route('admin.users.update', $this->admin), [
            'name' => $this->admin->name,
            'email' => $this->admin->email,
            'roles' => [],
        ])->assertRedirect();

        $this->assertTrue($this->admin->fresh()->hasRole('Super Admin'));
    }

    public function test_deactivating_a_user_ends_their_session_on_the_next_request(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('news.view');

        $this->actingAs($user)->get(route('admin.dashboard'))->assertOk();

        $user->update(['is_active' => false]);

        $this->actingAs($user->fresh())
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_the_user_screens_render(): void
    {
        $user = User::factory()->create();

        foreach ([
            route('admin.users.index'),
            route('admin.users.create'),
            route('admin.users.edit', $user),
            route('admin.roles.index'),
            route('admin.permissions.index'),
        ] as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
        }
    }

    public function test_a_user_without_permission_cannot_manage_users(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('news.view');

        $this->actingAs($viewer)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($viewer)->post(route('admin.users.store'), [
            'name' => 'X', 'email' => 'x@example.test',
            'password' => 'kata-sandi-rahasia', 'password_confirmation' => 'kata-sandi-rahasia',
        ])->assertForbidden();
    }
}

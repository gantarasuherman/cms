<?php

namespace Database\Seeders;

use App\Support\Permissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds the ability vocabulary and a set of starter roles. The roles are only
 * defaults: administrators may rename them, change their grants or add new
 * ones from /admin/roles afterwards.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Permissions::all() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $roles = [
            // Super Admin holds no explicit grants: it is short-circuited by a
            // Gate::before rule, so new modules are covered automatically.
            'Super Admin' => [],
            'Admin' => array_values(array_diff(Permissions::all(), ['role.delete', 'user.delete'])),
            'Editor' => [
                ...Permissions::forModule('news'),
                ...Permissions::forModule('page'),
                ...Permissions::forModule('category'),
                ...Permissions::forModule('tag'),
                ...Permissions::forModule('announcement'),
                ...Permissions::forModule('social_post'),
                'media.view', 'media.create',
                'faq.view', 'faq.create', 'faq.update',
            ],
            'Operator' => [
                // The people who actually work the complaint queue.
                ...Permissions::forModule('complaint'),
                'chatbot.view',
                'news.view', 'news.create', 'news.update',
                'service.view', 'service.create', 'service.update',
                'document.view', 'document.create', 'document.update',
                'faq.view', 'faq.create', 'faq.update',
                'media.view', 'media.create',
                'category.view',
            ],
            'Viewer' => collect(Permissions::modules())
                ->keys()
                ->map(fn (string $module) => "$module.view")
                ->all(),
        ];

        foreach ($roles as $name => $permissions) {
            Role::findOrCreate($name, 'web')->syncPermissions($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}


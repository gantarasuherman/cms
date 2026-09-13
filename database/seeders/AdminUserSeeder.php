<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = (string) env('ADMIN_EMAIL', 'admin@example.test');

        $user = User::withTrashed()->firstOrNew(['email' => $email]);

        $user->fill([
            'name' => (string) env('ADMIN_NAME', 'Super Admin'),
            'is_active' => true,
        ]);

        if (! $user->exists) {
            // Only set on creation so re-running the seeder never resets a
            // password an administrator has since changed.
            $user->password = Hash::make((string) env('ADMIN_PASSWORD', 'password'));
            $user->email_verified_at = now();
        }

        $user->deleted_at = null;
        $user->save();

        $user->syncRoles(['Super Admin']);
    }
}


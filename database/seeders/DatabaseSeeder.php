<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            IconSeeder::class,
            RolePermissionSeeder::class,
            AdminUserSeeder::class,
            MenuSeeder::class,
            SettingSeeder::class,
            HomepageSectionSeeder::class,
            CarouselSeeder::class,
        ]);
    }
}


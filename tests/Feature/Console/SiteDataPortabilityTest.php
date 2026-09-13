<?php

namespace Tests\Feature\Console;

use App\Models\AuditLog;
use App\Models\PublicMenu;
use App\Models\Setting;
use App\Models\User;
use App\Support\PortableData;
use Database\Seeders\HomepageSectionSeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SiteDataPortabilityTest extends TestCase
{
    use RefreshDatabase;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SettingSeeder::class);
        $this->seed(MenuSeeder::class);
        $this->seed(HomepageSectionSeeder::class);

        $this->path = storage_path('framework/testing/export-'.uniqid());
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->path);

        parent::tearDown();
    }

    private function export(array $options = []): void
    {
        $this->artisan('cms:export', ['--path' => $this->path] + $options)->assertSuccessful();
    }

    public function test_a_site_survives_the_trip_to_another_machine(): void
    {
        Setting::where('group', 'general')->where('key', 'site_name')->update(['value' => 'Portal Dinas PUPR']);
        $menus = PublicMenu::withoutGlobalScopes()->count();

        $this->export();

        // The other machine: a database with the schema and nothing in it.
        Setting::where('group', 'general')->where('key', 'site_name')->update(['value' => 'Situs Kosong']);
        PublicMenu::withoutGlobalScopes()->delete();

        $this->artisan('cms:import', ['path' => $this->path, '--force' => true])->assertSuccessful();

        $this->assertSame('Portal Dinas PUPR', Setting::where('group', 'general')->where('key', 'site_name')->value('value'));
        $this->assertSame($menus, PublicMenu::withoutGlobalScopes()->count());
    }

    public function test_a_nested_menu_keeps_its_tree(): void
    {
        // Rows inside one table point at each other, so the order within the
        // file cannot satisfy the foreign key on its own.
        $parent = PublicMenu::withoutGlobalScopes()->whereNotNull('parent_id')->firstOrFail();

        $this->export();
        PublicMenu::withoutGlobalScopes()->delete();
        $this->artisan('cms:import', ['path' => $this->path, '--force' => true])->assertSuccessful();

        $restored = PublicMenu::withoutGlobalScopes()->find($parent->getKey());

        $this->assertNotNull($restored);
        $this->assertSame($parent->parent_id, $restored->parent_id);
        $this->assertNotNull(PublicMenu::withoutGlobalScopes()->find($restored->parent_id));
    }

    public function test_a_secret_is_blanked_rather_than_carried_or_dropped(): void
    {
        Setting::updateOrCreate(
            ['group' => 'ai', 'key' => 'api_key'],
            ['value' => \Illuminate\Support\Facades\Crypt::encryptString('gsk-rahasia-sekali'), 'type' => 'encrypted'],
        );

        $this->export();

        $rows = json_decode(File::get($this->path.'/tables/settings.json'), true);
        $key = collect($rows)->firstWhere('key', 'api_key');

        // Present, so the admin screen still has its field — but empty, because
        // it is encrypted with this machine's APP_KEY and unreadable anywhere
        // else, and a key in a file that gets copied around is a leak.
        $this->assertNotNull($key, 'Barisnya tetap ada.');
        $this->assertNull($key['value']);
        $this->assertStringNotContainsString('gsk-rahasia-sekali', File::get($this->path.'/tables/settings.json'));
    }

    public function test_personal_and_machine_data_never_leaves(): void
    {
        $this->export(['--content' => true]);

        $carried = array_keys(json_decode(File::get($this->path.'/manifest.json'), true)['tables']);

        foreach (array_keys(PortableData::excluded()) as $table) {
            $this->assertNotContains($table, $carried, "$table tidak boleh ikut diekspor.");
            $this->assertFileDoesNotExist($this->path.'/tables/'.$table.'.json');
        }
    }

    public function test_importing_leaves_accounts_and_audit_trail_alone(): void
    {
        $this->export();

        $user = User::factory()->create();
        AuditLog::create(['user_id' => $user->getKey(), 'action' => 'update', 'module' => 'setting', 'record_id' => 1]);

        $this->artisan('cms:import', ['path' => $this->path, '--force' => true])->assertSuccessful();

        // The import replaces the tables it carries and no others: an
        // administrator must not lose their login by loading a config export.
        $this->assertDatabaseHas('users', ['id' => $user->getKey()]);
        $this->assertSame(1, AuditLog::count());
    }

    public function test_uploads_travel_with_the_data(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('settings/logo.png', 'GAMBAR-LOGO');

        $this->export();

        Storage::disk('public')->delete('settings/logo.png');
        $this->artisan('cms:import', ['path' => $this->path, '--force' => true])->assertSuccessful();

        Storage::disk('public')->assertExists('settings/logo.png');
        $this->assertSame('GAMBAR-LOGO', Storage::disk('public')->get('settings/logo.png'));
    }

    public function test_content_is_left_out_unless_it_is_asked_for(): void
    {
        $this->export();
        $this->assertFileDoesNotExist($this->path.'/tables/news.json');

        File::deleteDirectory($this->path);

        $this->export(['--content' => true]);
        $this->assertFileExists($this->path.'/tables/news.json');
    }

    public function test_foreign_key_checks_are_on_again_afterwards(): void
    {
        $this->export();
        $this->artisan('cms:import', ['path' => $this->path, '--force' => true])->assertSuccessful();

        // Asked of the database rather than of a flag, and by trying the thing
        // the constraint exists to stop: left off, every later write on this
        // connection would skip its foreign keys, and that failure surfaces
        // far from its cause.
        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('public_menus')->insert([
            'slug' => 'yatim', 'title' => 'Yatim', 'parent_id' => 987654321,
            'sort_order' => 0, 'is_active' => 1, 'target' => '_self',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}

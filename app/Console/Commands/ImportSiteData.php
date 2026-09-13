<?php

namespace App\Console\Commands;

use App\Support\PortableData;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Loads a folder written by `cms:export` into this machine.
 *
 * Destructive by nature: every table it carries is replaced, not merged. A
 * merge would have to guess whether a menu item with the same name is the same
 * item, and guessing wrong leaves a site with two navigations. So the rule is
 * plain — the export wins for the tables it contains, and touches no other.
 *
 * Nothing here writes accounts, audit logs, complaints or bot credentials:
 * those never leave the machine they were made on.
 */
class ImportSiteData extends Command
{
    use ConfirmableTrait;

    protected $signature = 'cms:import
        {path : Folder hasil cms:export, absolut atau relatif terhadap database/exports}
        {--no-media : Jangan salin berkas unggahan}
        {--force : Jalankan tanpa bertanya}';

    protected $description = 'Muat konfigurasi situs dari folder hasil cms:export';

    public function handle(): int
    {
        $path = $this->resolve($this->argument('path'));

        if ($path === null) {
            $this->error('Folder tidak ditemukan: '.$this->argument('path'));

            return self::FAILURE;
        }

        $manifest = json_decode((string) File::get($path.'/manifest.json'), true);

        if (! is_array($manifest)) {
            $this->error('manifest.json tidak terbaca. Folder ini bukan hasil cms:export.');

            return self::FAILURE;
        }

        $tables = array_keys($manifest['tables'] ?? []);

        $this->line('Dibuat  : '.($manifest['exported_at'] ?? '?'));
        $this->line('Tabel   : '.count($tables).' — '.array_sum($manifest['tables'] ?? []).' baris');
        $this->line('Unggahan: '.($manifest['media_files'] ?? 0).' berkas');
        $this->newLine();
        $this->warn('Seluruh isi tabel di atas akan DIGANTI oleh isi folder ini.');

        // Laravel's own guard: in production this refuses unless --force.
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        $loaded = $this->load($path, $tables);
        $media = $this->option('no-media') ? 0 : $this->restoreMedia($path);

        $this->call('cache:clear');
        $this->call('view:clear');

        $this->newLine();
        $this->info('Selesai.');
        $this->table(['Tabel', 'Baris'], array_map(null, array_keys($loaded), array_values($loaded)));
        $this->line('Berkas unggahan: '.$media);

        if (filled($manifest['secrets_omitted'] ?? [])) {
            $this->newLine();
            $this->comment('Kunci yang sengaja tidak ikut dan perlu diisi ulang di panel admin:');

            foreach ($manifest['secrets_omitted'] as $secret) {
                $this->line('  · '.$secret);
            }
        }

        $this->newLine();
        $this->comment('Belum ada akun admin di mesin ini? php artisan db:seed --class=AdminUserSeeder');

        return self::SUCCESS;
    }

    /**
     * @param  array<int, string>  $tables
     * @return array<string, int>
     */
    private function load(string $path, array $tables): array
    {
        $loaded = [];

        // Foreign keys off for the duration: the tables are written parent
        // first, but a self-referencing tree — a menu whose parent is another
        // menu — cannot be ordered within its own table.
        Schema::disableForeignKeyConstraints();

        try {
            DB::transaction(function () use ($path, $tables, &$loaded) {
                foreach ($tables as $table) {
                    $file = $path.'/tables/'.$table.'.json';

                    if (! File::exists($file) || ! Schema::hasTable($table)) {
                        $this->warn("  ~ $table dilewati (berkas atau tabelnya tidak ada).");

                        continue;
                    }

                    $rows = json_decode((string) File::get($file), true) ?: [];

                    DB::table($table)->delete();

                    // In pages: one insert per row would be thousands of
                    // round trips for a site with a real archive.
                    foreach (array_chunk($rows, 200) as $chunk) {
                        DB::table($table)->insert($chunk);
                    }

                    $loaded[$table] = count($rows);
                }
            });
        } finally {
            // Even if the transaction rolled back: leaving constraints off
            // would silently disarm every foreign key for this connection.
            Schema::enableForeignKeyConstraints();
        }

        return $loaded;
    }

    private function restoreMedia(string $path): int
    {
        $source = $path.'/storage';

        if (! File::isDirectory($source)) {
            return 0;
        }

        $disk = Storage::disk('public');
        $copied = 0;

        foreach (File::allFiles($source) as $file) {
            // Written to the *relative* path, so the uploads land where the
            // stored column values already point.
            $disk->put(
                str_replace('\\', '/', $file->getRelativePathname()),
                File::get($file->getPathname()),
            );
            $copied++;
        }

        return $copied;
    }

    private function resolve(string $path): ?string
    {
        foreach ([$path, base_path('database/exports/'.$path)] as $candidate) {
            if (File::isDirectory($candidate)) {
                return rtrim($candidate, '/');
            }
        }

        return null;
    }
}

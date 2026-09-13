<?php

namespace App\Console\Commands;

use App\Support\PortableData;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Writes the site's configuration — and optionally its content — to a folder
 * that can be copied to another machine and loaded with `cms:import`.
 *
 * Not a `mysqldump`: that would carry login accounts, audit trails, visitor
 * statistics and citizens' complaints to a machine with no business holding
 * them, plus secrets encrypted under an APP_KEY the other machine lacks.
 * `App\Support\PortableData` declares what travels and states why the rest
 * does not.
 *
 * JSON rather than SQL, so the result is readable, diffable, and loads on any
 * database the application supports rather than only on MySQL.
 */
class ExportSiteData extends Command
{
    protected $signature = 'cms:export
        {--path= : Folder tujuan (bawaan: database/exports/YYYY-MM-DD-HHMM)}
        {--content : Sertakan berita, halaman, layanan, dokumen, FAQ, dan unggahan}
        {--no-media : Jangan salin berkas unggahan}';

    protected $description = 'Simpan konfigurasi situs ke folder yang bisa dipindahkan ke mesin lain';

    public function handle(): int
    {
        $path = $this->option('path') ?: base_path('database/exports/'.now()->format('Y-m-d-Hi'));

        if (File::exists($path) && ! $this->confirm("Folder $path sudah ada. Timpa?", false)) {
            $this->warn('Dibatalkan.');

            return self::FAILURE;
        }

        File::ensureDirectoryExists($path.'/tables');

        $tables = PortableData::configuration();

        if ($this->option('content')) {
            $tables = [...$tables, ...PortableData::content()];
        }

        $counts = [];

        foreach ($tables as $table) {
            $rows = $this->rowsOf($table);

            if ($rows === null) {
                // A table the schema does not have: an export taken against
                // an older or newer migration state should say so rather than
                // stop, because everything else in it is still usable.
                $this->warn("  ~ $table tidak ada di basis data ini, dilewati.");

                continue;
            }

            File::put(
                $path.'/tables/'.$table.'.json',
                json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n",
            );

            $counts[$table] = count($rows);
        }

        $media = $this->option('no-media') ? 0 : $this->copyMedia($path);

        File::put($path.'/manifest.json', json_encode([
            'exported_at' => now()->toIso8601String(),
            'app_name' => config('app.name'),
            'includes_content' => (bool) $this->option('content'),
            'includes_media' => ! $this->option('no-media'),
            'tables' => $counts,
            'media_files' => $media,
            // Whoever imports this needs to know which secrets were blanked.
            'secrets_omitted' => array_values(array_map(
                fn (array $s) => $s['table'].'.'.$s['column'].' ('.json_encode($s['match']).')',
                PortableData::secrets(),
            )),
            'excluded_tables' => PortableData::excluded(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");

        $this->newLine();
        $this->info('Tersimpan di '.$path);
        $this->table(['Tabel', 'Baris'], array_map(null, array_keys($counts), array_values($counts)));
        $this->line('Berkas unggahan: '.$media);
        $this->newLine();
        $this->comment('Di mesin tujuan: php artisan migrate --force lalu php artisan cms:import '.basename($path));
        $this->comment('Kunci API dan kredensial bot tidak ikut — isi ulang lewat panel admin setelah impor.');

        return self::SUCCESS;
    }

    /**
     * @return array<int, array<string, mixed>>|null  Null when the table is absent.
     */
    private function rowsOf(string $table): ?array
    {
        if (! DB::getSchemaBuilder()->hasTable($table)) {
            return null;
        }

        $rows = DB::table($table)->get()->map(fn ($row) => (array) $row)->all();

        foreach (PortableData::secrets() as $secret) {
            if ($secret['table'] !== $table) {
                continue;
            }

            $rows = array_map(function (array $row) use ($secret) {
                foreach ($secret['match'] as $column => $value) {
                    if (($row[$column] ?? null) !== $value) {
                        return $row;
                    }
                }

                // Blanked, not removed: the row is what tells the admin screen
                // the setting exists at all.
                $row[$secret['column']] = null;

                return $row;
            }, $rows);
        }

        return $rows;
    }

    /**
     * Copies the public disk wholesale.
     *
     * Wholesale rather than only the files the exported rows mention: a
     * picture is referenced from settings, slides, articles and posts alike,
     * and a reference this code failed to think of would arrive on the other
     * machine as a broken image. The disk is small — it holds uploads, not
     * backups — so copying it entire is cheaper than being clever.
     */
    private function copyMedia(string $path): int
    {
        $disk = Storage::disk('public');
        $files = $disk->allFiles();
        $copied = 0;

        foreach ($files as $file) {
            if (str_ends_with($file, '.gitignore')) {
                continue;
            }

            $target = $path.'/storage/'.$file;
            File::ensureDirectoryExists(dirname($target));
            File::put($target, $disk->get($file));
            $copied++;
        }

        return $copied;
    }
}

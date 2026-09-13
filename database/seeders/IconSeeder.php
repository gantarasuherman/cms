<?php

namespace Database\Seeders;

use App\Models\Icon;
use App\Services\Icons\IconRepository;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Dumps the generated icon set into the database so the admin picker has a
 * real catalogue to offer.
 *
 * config/icons.php stays the source (regenerate it with
 * `node scripts/build-icons.mjs`); re-running this seeder syncs the table to
 * it without disturbing icons already chosen elsewhere.
 */
class IconSeeder extends Seeder
{
    /**
     * Grouping for the picker. Anything not listed lands in "Lainnya", so a
     * newly generated icon still appears rather than silently vanishing.
     *
     * @var array<string, array<int, string>>
     */
    private const GROUPS = [
        'Navigasi' => ['house', 'layout-dashboard', 'menu', 'panels-top-left', 'chevron-right', 'chevron-left',
            'chevron-up', 'chevron-down', 'external-link', 'link', 'log-out', 'search', 'move', 'grip-vertical'],
        'Konten' => ['newspaper', 'file-text', 'files', 'folder', 'folder-tree', 'tag', 'tags', 'book-open',
            'library', 'layers', 'list', 'list-ordered', 'pencil', 'archive', 'copy', 'save', 'star', 'pin',
            'megaphone', 'calendar', 'clock', 'history'],
        'Media' => ['image', 'images', 'video', 'paperclip', 'upload', 'download', 'palette', 'contrast'],
        'Layanan' => ['briefcase', 'handshake', 'clipboard-list', 'receipt', 'banknote', 'workflow',
            'circle-help', 'building-2', 'map-pin', 'phone', 'mail', 'globe', 'send'],
        'Pengguna & Akses' => ['users', 'user', 'user-plus', 'shield', 'shield-check', 'key-round', 'lock'],
        'Sistem' => ['settings', 'database', 'server', 'activity', 'filter', 'plus', 'trash-2', 'eye', 'eye-off',
            'loader-circle', 'sun', 'moon', 'accessibility', 'type', 'volume-2', 'pause', 'play', 'square'],
        'Status' => ['circle-check', 'circle-alert', 'triangle-alert', 'info', 'circle-slash', 'check', 'x', 'circle'],
    ];

    public function run(): void
    {
        $icons = config('icons', []);

        if ($icons === []) {
            $this->command?->warn('config/icons.php kosong — jalankan: node scripts/build-icons.mjs');

            return;
        }

        $position = $this->positions();

        foreach ($icons as $name => $body) {
            [$group, $sort] = $position[$name] ?? ['Lainnya', 999];

            // Matched by name: an icon already referenced by a menu or category
            // keeps working, and only its artwork and grouping are refreshed.
            Icon::updateOrCreate(
                ['name' => $name],
                [
                    'label' => Str::headline($name),
                    'group' => $group,
                    'body' => $body,
                    'sort_order' => $sort,
                    'is_active' => true,
                ],
            );
        }

        // Icons dropped from the generated set are deactivated rather than
        // deleted, so anything still pointing at one does not break.
        Icon::whereNotIn('name', array_keys($icons))->update(['is_active' => false]);

        app(IconRepository::class)->forget();

        $this->command?->info('Bank ikon: '.count($icons).' ikon tersimpan.');
    }

    /** @return array<string, array{0: string, 1: int}> */
    private function positions(): array
    {
        $map = [];

        foreach (self::GROUPS as $group => $names) {
            foreach ($names as $index => $name) {
                $map[$name] = [$group, ($index + 1) * 10];
            }
        }

        return $map;
    }
}


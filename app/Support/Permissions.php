<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * The permission catalogue: the single source of truth behind the seeded
 * permissions, the role matrix screen and every policy check.
 *
 * Roles themselves stay fully dynamic (administrators create and edit them);
 * what is fixed here is the *vocabulary* of abilities the code enforces, since
 * an ability nothing checks would be a lie in the UI.
 */
final class Permissions
{
    public const VIEW = 'view';

    public const CREATE = 'create';

    public const UPDATE = 'update';

    public const DELETE = 'delete';

    public const PUBLISH = 'publish';

    /**
     * @return array<string, array{label: string, icon: string, abilities: array<int, string>}>
     */
    public static function modules(): array
    {
        $crud = [self::VIEW, self::CREATE, self::UPDATE, self::DELETE];
        $publishable = [...$crud, self::PUBLISH];

        return [
            'news' => ['label' => 'Berita', 'icon' => 'newspaper', 'abilities' => $publishable],
            'category' => ['label' => 'Kategori', 'icon' => 'folder-tree', 'abilities' => $crud],
            'tag' => ['label' => 'Tag', 'icon' => 'tag', 'abilities' => $crud],
            'page' => ['label' => 'Halaman', 'icon' => 'file-text', 'abilities' => $publishable],
            'service' => ['label' => 'Layanan', 'icon' => 'briefcase', 'abilities' => $crud],
            'document' => ['label' => 'Dokumen', 'icon' => 'files', 'abilities' => $crud],
            'faq' => ['label' => 'FAQ', 'icon' => 'circle-help', 'abilities' => $crud],
            'announcement' => ['label' => 'Pengumuman', 'icon' => 'megaphone', 'abilities' => $crud],
            'social_post' => ['label' => 'Unggahan Sosial', 'icon' => 'image', 'abilities' => $crud],
            // Complaints are never *created* from the admin panel — they only
            // arrive through a channel — so the vocabulary omits `create`.
            'complaint' => ['label' => 'Pengaduan', 'icon' => 'clipboard-list', 'abilities' => [self::VIEW, self::UPDATE, self::DELETE]],
            'chatbot' => ['label' => 'Chatbot', 'icon' => 'workflow', 'abilities' => $crud],
            'media' => ['label' => 'Media', 'icon' => 'images', 'abilities' => [self::VIEW, self::CREATE, self::DELETE]],
            'menu' => ['label' => 'Menu', 'icon' => 'list', 'abilities' => $crud],
            'settings' => ['label' => 'Pengaturan', 'icon' => 'settings', 'abilities' => [self::VIEW, self::UPDATE]],
            'user' => ['label' => 'Pengguna', 'icon' => 'users', 'abilities' => $crud],
            'role' => ['label' => 'Peran', 'icon' => 'shield', 'abilities' => $crud],
            'audit_log' => ['label' => 'Audit Log', 'icon' => 'history', 'abilities' => [self::VIEW]],
        ];
    }

    /** Every ability name used anywhere, ordered by module. @return array<int, string> */
    public static function all(): array
    {
        return collect(self::modules())
            ->flatMap(fn (array $module, string $name) => collect($module['abilities'])->map(fn (string $a) => "$name.$a"))
            ->values()
            ->all();
    }

    /** Ability names for one module. @return array<int, string> */
    public static function forModule(string $module): array
    {
        return collect(self::modules()[$module]['abilities'] ?? [])
            ->map(fn (string $ability) => "$module.$ability")
            ->all();
    }

    /** Distinct ability columns, in display order, for the role matrix header. @return Collection<int, string> */
    public static function abilityColumns(): Collection
    {
        return collect(self::modules())
            ->flatMap(fn (array $module) => $module['abilities'])
            ->unique()
            ->values();
    }

    public static function abilityLabel(string $ability): string
    {
        return match ($ability) {
            self::VIEW => 'Lihat',
            self::CREATE => 'Tambah',
            self::UPDATE => 'Ubah',
            self::DELETE => 'Hapus',
            self::PUBLISH => 'Terbitkan',
            default => ucfirst($ability),
        };
    }

    public static function exists(string $permission): bool
    {
        return in_array($permission, self::all(), true);
    }
}


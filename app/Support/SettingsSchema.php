<?php

namespace App\Support;

use App\Services\Theme\ThemeService;
use Illuminate\Validation\Rule;

/**
 * Declares what each settings screen contains: the fields, their types, their
 * validation and their wording.
 *
 * One definition drives the form, the validation and the write, so a new
 * setting is added here and nowhere else. Values themselves live in the
 * `settings` table and are always editable by an administrator — nothing here
 * hard-codes a value, only its shape.
 */
final class SettingsSchema
{
    /**
     * @return array<string, array{title: string, description: string, icon: string, fields: array<string, array<string, mixed>>}>
     */
    public static function groups(): array
    {
        return [
            'general' => [
                'title' => 'Pengaturan Umum',
                'description' => 'Identitas situs yang tampil di seluruh halaman.',
                'icon' => 'settings',
                'fields' => [
                    'site_name' => ['label' => 'Nama Situs', 'type' => 'text', 'rules' => ['required', 'string', 'max:120']],
                    'site_description' => ['label' => 'Deskripsi Singkat', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:500']],
                    'logo' => ['label' => 'Logo', 'type' => 'image', 'hint' => 'PNG, JPG, WebP, atau SVG. Maksimal 2 MB.'],
                    'favicon' => ['label' => 'Favicon', 'type' => 'image', 'hint' => 'Disarankan PNG 32×32 atau SVG. Juga dipakai sebagai lambang panel admin saat sidebar diciutkan.'],
                    'admin_logo' => ['label' => 'Logo Panel Admin', 'type' => 'image', 'hint' => 'Tampil di sidebar admin. Kosongkan untuk memakai logo situs. Lebar memanjang, tinggi sekitar 40 piksel.'],
                    'email' => ['label' => 'Email', 'type' => 'email', 'rules' => ['nullable', 'email', 'max:120']],
                    'phone' => ['label' => 'Telepon', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:40']],
                    'address' => ['label' => 'Alamat', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:500']],
                    'website' => ['label' => 'Situs Resmi', 'type' => 'url', 'rules' => ['nullable', 'url', 'max:255']],
                    'copyright' => ['label' => 'Teks Hak Cipta', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:255']],
                    'header_cta_text' => ['label' => 'Tombol Header', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:40'], 'hint' => 'Tombol di sisi kanan navbar. Kosongkan agar tidak ditampilkan.'],
                    'header_cta_link' => ['label' => 'Tautan Tombol Header', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:255', 'regex:/^(https?:\/\/|\/|mailto:|tel:)/'], 'hint' => 'Keduanya harus diisi agar tombol muncul.'],
                ],
            ],

            'seo' => [
                'title' => 'SEO',
                'description' => 'Nilai bawaan untuk halaman yang tidak menetapkan SEO-nya sendiri.',
                'icon' => 'search',
                'fields' => [
                    'seo_title' => ['label' => 'Judul Bawaan', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:120']],
                    'seo_description' => ['label' => 'Deskripsi Bawaan', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:300'], 'hint' => 'Idealnya 150–160 karakter.'],
                    'seo_keywords' => ['label' => 'Kata Kunci', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:255'], 'hint' => 'Pisahkan dengan koma.'],
                    'og_image' => ['label' => 'Gambar Berbagi (OG Image)', 'type' => 'image', 'hint' => 'Disarankan 1200×630 piksel.'],
                    'robots' => ['label' => 'Robots', 'type' => 'select', 'options' => [
                        'index, follow' => 'index, follow — tampil di mesin pencari',
                        'noindex, follow' => 'noindex, follow — sembunyikan halaman, ikuti tautan',
                        'index, nofollow' => 'index, nofollow',
                        'noindex, nofollow' => 'noindex, nofollow — sembunyikan seluruhnya',
                    ], 'rules' => ['nullable', 'string', 'max:64']],
                    'canonical' => ['label' => 'URL Kanonik', 'type' => 'url', 'rules' => ['nullable', 'url', 'max:255'], 'hint' => 'Kosongkan agar memakai URL halaman itu sendiri.'],
                ],
            ],

            'appearance' => [
                'title' => 'Tampilan',
                'description' => 'Warna dan jenis huruf halaman publik. Perubahan langsung berlaku di seluruh halaman.',
                'icon' => 'palette',
                'fields' => [
                    'primary_color' => [
                        'label' => 'Warna Utama',
                        'type' => 'color',
                        'hint' => 'Dipakai header, tautan, tombol utama, dan penanda menu aktif. Seluruh gradasi terang–gelapnya diturunkan otomatis dari warna ini.',
                    ],
                    'secondary_color' => [
                        'label' => 'Warna Pendukung',
                        'type' => 'color',
                        'hint' => 'Aksen seperti label kategori pada slider beranda.',
                    ],
                    'footer_color' => [
                        'label' => 'Warna Footer',
                        'type' => 'color',
                        'hint' => 'Latar bagian bawah halaman. Warna teks, tautan, dan garisnya diturunkan otomatis agar tetap terbaca — baik pada latar terang maupun gelap.',
                    ],
                    'font_family' => [
                        'label' => 'Jenis Huruf',
                        'type' => 'select',
                        'options' => ThemeService::fontOptions(),
                        'rules' => ['nullable', 'string', Rule::in(array_keys(ThemeService::FONTS))],
                        'hint' => 'Seluruh pilihan tersedia tanpa memuat berkas dari pihak ketiga, jadi alamat IP pengunjung tidak ikut terkirim ke mana pun.',
                    ],
                    'instagram_embed' => [
                        'label' => 'Pakai sematan resmi Instagram',
                        'type' => 'boolean',
                        'hint' => 'Unggahan Instagram ditampilkan langsung oleh Instagram, jadi tampilannya persis unggahan aslinya — termasuk carousel, video, dan jumlah suka yang selalu terbaru. Perlu diketahui: sematan ini memuat skrip dari instagram.com, sehingga alamat IP setiap pengunjung beranda ikut terkirim ke Meta sebelum mereka melakukan apa pun. Matikan untuk kembali ke kartu buatan situs ini, yang melayani gambarnya dari server sendiri.',
                    ],
                ],
            ],

            'accessibility' => [
                'title' => 'Aksesibilitas',
                'description' => 'Menentukan kendali aksesibilitas mana yang tersedia bagi pengunjung situs publik.',
                'icon' => 'accessibility',
                'fields' => [
                    'accessibility_enabled' => ['label' => 'Aktifkan fitur aksesibilitas', 'type' => 'boolean', 'hint' => 'Mematikan ini menyembunyikan seluruh kendali dari pengunjung.'],
                    'show_widget' => ['label' => 'Tampilkan tombol aksesibilitas', 'type' => 'boolean'],
                    'widget_position' => ['label' => 'Posisi tombol', 'type' => 'select', 'options' => [
                        'bottom-right' => 'Kanan bawah',
                        'bottom-left' => 'Kiri bawah',
                        'top-right' => 'Kanan atas',
                        'top-left' => 'Kiri atas',
                    ], 'rules' => ['nullable', 'string', 'max:32']],
                    'text_resize_enabled' => ['label' => 'Perbesar / perkecil teks', 'type' => 'boolean'],
                    'color_blind_mode_enabled' => ['label' => 'Mode buta warna', 'type' => 'boolean', 'hint' => 'Protanopia, deuteranopia, tritanopia, dan skala abu-abu.'],
                    'text_to_speech_enabled' => ['label' => 'Baca halaman (text-to-speech)', 'type' => 'boolean'],
                    'highlight_links_enabled' => ['label' => 'Pertegas tautan dan tombol', 'type' => 'boolean'],
                    'keyboard_navigation_enabled' => ['label' => 'Bantuan navigasi papan ketik', 'type' => 'boolean'],
                    'reduce_motion_enabled' => ['label' => 'Kurangi animasi', 'type' => 'boolean'],
                ],
            ],

            'footer' => [
                'title' => 'Footer',
                'description' => 'Bagian bawah setiap halaman publik.',
                'icon' => 'panels-top-left',
                'fields' => [
                    'footer_about' => ['label' => 'Tentang Singkat', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:600']],
                    'footer_text' => ['label' => 'Teks Tambahan', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:600']],
                    'show_social' => ['label' => 'Tampilkan tautan media sosial', 'type' => 'boolean'],
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public static function group(string $name): array
    {
        abort_unless(isset(self::groups()[$name]), 404);

        return self::groups()[$name];
    }

    /**
     * Validation rules for one screen, derived from its field declarations.
     *
     * @return array<string, mixed>
     */
    public static function rules(string $group): array
    {
        $rules = [];

        foreach (self::group($group)['fields'] as $key => $field) {
            $rules[$key] = match ($field['type']) {
                'boolean' => ['nullable', 'boolean'],
                'color' => ['required', 'string', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
                // The upload is a separate input; the stored value is a path.
                'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
                'integer' => ['nullable', 'integer', 'min:0'],
                default => $field['rules'] ?? ['nullable', 'string', 'max:255'],
            };
        }

        return $rules;
    }

    /**
     * Storage type per key, so SettingService writes a boolean as a boolean
     * rather than the string "1".
     *
     * @return array<string, string>
     */
    public static function types(string $group): array
    {
        $types = [];

        foreach (self::group($group)['fields'] as $key => $field) {
            $types[$key] = match ($field['type']) {
                'boolean' => 'boolean',
                'integer' => 'integer',
                'color' => 'string',
                'image' => 'image',
                'textarea' => 'text',
                default => 'string',
            };
        }

        return $types;
    }
}


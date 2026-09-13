<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Services\Settings\SettingService;
use Illuminate\Database\Seeder;

/**
 * Default values only. Every key here is editable from /admin/settings, and
 * existing values are never overwritten when the seeder is re-run.
 */
class SettingSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->defaults() as $group => $entries) {
            foreach ($entries as $key => [$value, $type]) {
                Setting::firstOrCreate(
                    ['group' => $group, 'key' => $key],
                    ['value' => $value, 'type' => $type],
                );
            }
        }

        app(SettingService::class)->forget();
    }

    /** @return array<string, array<string, array{0: ?string, 1: string}>> */
    private function defaults(): array
    {
        return [
            'general' => [
                'site_name' => ['Dynamic CMS', 'string'],
                'site_description' => ['Portal informasi dan layanan publik.', 'string'],
                'logo' => [null, 'image'],
                'favicon' => [null, 'image'],
                'admin_logo' => [null, 'image'],
                'email' => ['info@example.test', 'string'],
                'phone' => [null, 'string'],
                'address' => [null, 'text'],
                'website' => [null, 'string'],
                'copyright' => ['© '.date('Y').' Dynamic CMS. Hak cipta dilindungi.', 'string'],
                'header_cta_text' => [null, 'string'],
                'header_cta_link' => [null, 'string'],
            ],
            'seo' => [
                'seo_title' => ['Dynamic CMS', 'string'],
                'seo_description' => ['Portal informasi dan layanan publik.', 'text'],
                'seo_keywords' => ['cms, informasi publik, layanan', 'string'],
                'og_image' => [null, 'image'],
                'robots' => ['index, follow', 'string'],
                'canonical' => [null, 'string'],
            ],
            'appearance' => [
                'primary_color' => [\App\Services\Theme\ThemeService::DEFAULT_PRIMARY, 'string'],
                'secondary_color' => [\App\Services\Theme\ThemeService::DEFAULT_SECONDARY, 'string'],
                'footer_color' => [\App\Services\Theme\ThemeService::DEFAULT_FOOTER, 'string'],
                'font_family' => [\App\Services\Theme\ThemeService::DEFAULT_FONT, 'string'],
                'instagram_embed' => ['1', 'boolean'],
            ],
            'accessibility' => [
                'accessibility_enabled' => ['1', 'boolean'],
                'show_widget' => ['1', 'boolean'],
                'widget_position' => ['bottom-right', 'string'],
                'text_resize_enabled' => ['1', 'boolean'],
                'color_blind_mode_enabled' => ['1', 'boolean'],
                'text_to_speech_enabled' => ['1', 'boolean'],
                'highlight_links_enabled' => ['1', 'boolean'],
                'keyboard_navigation_enabled' => ['1', 'boolean'],
                'reduce_motion_enabled' => ['1', 'boolean'],
            ],
            'footer' => [
                'footer_about' => ['Portal resmi yang menyajikan berita, layanan, dan dokumen publik.', 'text'],
                'footer_text' => [null, 'text'],
                'show_social' => ['1', 'boolean'],
            ],
            'homepage' => [
                'featured_news_count' => ['3', 'integer'],
                'latest_news_count' => ['6', 'integer'],
            ],
        ];
    }
}


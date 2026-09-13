<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\News;
use App\Models\Page;
use App\Models\Service;
use App\Services\Settings\SettingService;
use Illuminate\Http\Response;

/**
 * Sitemap and robots.txt, generated from what is actually published rather
 * than from a static file that drifts out of date.
 */
class SitemapController extends Controller
{
    public function index(): Response
    {
        $urls = collect();

        $urls->push(['loc' => route('public.home'), 'priority' => '1.0', 'changefreq' => 'daily']);

        foreach ([
            'public.news.index' => 'daily',
            'public.services.index' => 'weekly',
            'public.documents.index' => 'weekly',
            'public.faq.index' => 'monthly',
        ] as $route => $frequency) {
            $urls->push(['loc' => route($route), 'priority' => '0.8', 'changefreq' => $frequency]);
        }

        News::published()->select('slug', 'updated_at')->get()->each(fn (News $news) => $urls->push([
            'loc' => route('public.news.show', $news->slug),
            'lastmod' => $news->updated_at?->toAtomString(),
            'priority' => '0.7',
            'changefreq' => 'monthly',
        ]));

        Service::published()->select('slug', 'updated_at')->get()->each(fn (Service $service) => $urls->push([
            'loc' => route('public.services.show', $service->slug),
            'lastmod' => $service->updated_at?->toAtomString(),
            'priority' => '0.7',
            'changefreq' => 'monthly',
        ]));

        Document::visible()->select('slug', 'updated_at')->get()->each(fn (Document $document) => $urls->push([
            'loc' => route('public.documents.show', $document->slug),
            'lastmod' => $document->updated_at?->toAtomString(),
            'priority' => '0.6',
            'changefreq' => 'monthly',
        ]));

        Page::published()->select('slug', 'updated_at')->get()->each(fn (Page $page) => $urls->push([
            'loc' => route('public.pages.show', $page->slug),
            'lastmod' => $page->updated_at?->toAtomString(),
            'priority' => '0.6',
            'changefreq' => 'monthly',
        ]));

        return response()
            ->view('sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml');
    }

    public function robots(SettingService $settings): Response
    {
        // A site set to noindex must not invite crawlers through robots.txt
        // either; the two would otherwise contradict each other.
        $blocked = str_contains((string) $settings->get('seo', 'robots', 'index, follow'), 'noindex');

        $lines = $blocked
            ? ['User-agent: *', 'Disallow: /']
            : ['User-agent: *', 'Disallow: /admin', 'Disallow: /pencarian', '', 'Sitemap: '.route('sitemap')];

        return response(implode(PHP_EOL, $lines).PHP_EOL)
            ->header('Content-Type', 'text/plain');
    }
}


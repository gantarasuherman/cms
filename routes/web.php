<?php

use App\Http\Controllers\Public\DocumentController;
use App\Http\Controllers\Public\DocumentDownloadController;
use App\Http\Controllers\Public\FaqController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\NewsController;
use App\Http\Controllers\Public\PageController;
use App\Http\Controllers\Public\SearchController;
use App\Http\Controllers\Public\ServiceController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

/*
| Public routes.
|
| Read-only by design: the only non-GET verb the public surface exposes is
| none at all. Everything that writes lives behind /admin.
*/

Route::name('public.')->group(function () {
    Route::get('/', HomeController::class)->name('home');

    Route::get('berita', [NewsController::class, 'index'])->name('news.index');
    Route::get('berita/{slug}', [NewsController::class, 'show'])->name('news.show');

    Route::get('layanan', [ServiceController::class, 'index'])->name('services.index');
    Route::get('layanan/{slug}', [ServiceController::class, 'show'])->name('services.show');

    Route::get('dokumen', [DocumentController::class, 'index'])->name('documents.index');
    // Registered before the {slug} route so "dokumen/x/unduh" is never
    // swallowed as a document slug.
    Route::get('dokumen/{slug}/unduh', DocumentDownloadController::class)
        ->middleware('throttle:downloads')
        ->name('documents.download');
    Route::get('dokumen/{slug}', [DocumentController::class, 'show'])->name('documents.show');

    Route::get('faq', FaqController::class)->name('faq.index');

    Route::get('pencarian', SearchController::class)
        ->middleware('throttle:search')
        ->name('search');

    // Catch-all for CMS pages. Last, so it never shadows a real route.
    Route::get('halaman/{slug}', PageController::class)->name('pages.show');
});

Route::get('sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::get('robots.txt', [SitemapController::class, 'robots'])->name('robots');

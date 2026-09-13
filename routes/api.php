<?php

use App\Http\Controllers\Api\Bot\ConfigController;
use App\Http\Controllers\Api\Bot\InboundController;
use App\Http\Controllers\Api\Bot\OutboxController;
use App\Http\Controllers\Api\Public\ContentController;
use Illuminate\Support\Facades\Route;

/*
| Public, read-only API.
|
| Only GET routes are registered here. There is deliberately no mutating
| endpoint: the public surface cannot create, update or delete anything.
*/

Route::prefix('public')
    ->name('api.public.')
    ->middleware('throttle:api')
    ->group(function () {
        Route::get('home', [ContentController::class, 'home'])->name('home');
        Route::get('menus', [ContentController::class, 'menus'])->name('menus');
        Route::get('settings', [ContentController::class, 'settings'])->name('settings');
        Route::get('carousel', [ContentController::class, 'carousel'])->name('carousel');

        Route::get('news', [ContentController::class, 'newsIndex'])->name('news.index');
        Route::get('news/categories', [ContentController::class, 'newsCategories'])->name('news.categories');
        Route::get('news/{slug}', [ContentController::class, 'newsShow'])->name('news.show');

        Route::get('services', [ContentController::class, 'servicesIndex'])->name('services.index');
        Route::get('services/categories', [ContentController::class, 'serviceCategories'])->name('services.categories');
        Route::get('services/{slug}', [ContentController::class, 'servicesShow'])->name('services.show');

        Route::get('documents', [ContentController::class, 'documentsIndex'])->name('documents.index');
        Route::get('documents/{slug}', [ContentController::class, 'documentsShow'])->name('documents.show');

        Route::get('faqs', [ContentController::class, 'faqs'])->name('faqs');

        Route::get('pages/{slug}', [ContentController::class, 'page'])->name('pages.show');
    });

/*
| Internal bot API.
|
| Not public in any sense: this runs the conversation engine and reads
| complaints, so every route sits behind the shared secret in
| `config/bot.php`. The Python service is the only caller — it owns the
| platform webhooks, verifies their signatures against the raw body (which
| only it has), downloads media, and posts a normalised message here.
|
| Throttled well above ordinary traffic: a busy hour of a public complaint
| line is still a handful of messages a second, and a cap protects the engine
| from a platform replaying a backlog at it.
*/

Route::prefix('bot')
    ->name('api.bot.')
    ->middleware(['bot.token', 'throttle:120,1'])
    ->group(function () {
        Route::get('config', ConfigController::class)->name('config');
        Route::post('inbound', InboundController::class)->name('inbound');
        Route::post('outbox/pull', [OutboxController::class, 'pull'])->name('outbox.pull');
        Route::post('outbox/report', [OutboxController::class, 'report'])->name('outbox.report');
    });

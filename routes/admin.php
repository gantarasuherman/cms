<?php

use App\Http\Controllers\Admin\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Admin\AiSettingsController;
use App\Http\Controllers\Admin\AnnouncementController;
use App\Http\Controllers\Admin\BotChannelController;
use App\Http\Controllers\Admin\BotConversationController;
use App\Http\Controllers\Admin\BotDataSourceController;
use App\Http\Controllers\Admin\BotFlowController;
use App\Http\Controllers\Admin\BotRecipientController;
use App\Http\Controllers\Admin\BotSimulatorController;
use App\Http\Controllers\Admin\BotQuestionController;
use App\Http\Controllers\Admin\ComplaintCategoryController;
use App\Http\Controllers\Admin\ComplaintController;
use App\Http\Controllers\Admin\DispositionTargetController;
use App\Http\Controllers\Admin\MapTileController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\Documents\DocumentController;
use App\Http\Controllers\Admin\FaqController;
use App\Http\Controllers\Admin\IconController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\News\TagController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\News\NewsController;
use App\Http\Controllers\Admin\Services\RequirementController;
use App\Http\Controllers\Admin\Settings\CarouselController;
use App\Http\Controllers\Admin\Settings\HomepageController;
use App\Http\Controllers\Admin\SocialPostController;
use App\Http\Controllers\Admin\Settings\SettingsController;
use App\Http\Controllers\Admin\Settings\SocialLinkController;
use App\Http\Controllers\Admin\Services\ServiceController;
use App\Http\Controllers\Admin\Services\StepController;
use App\Http\Controllers\Admin\Services\TariffController;
use App\Http\Controllers\Admin\Users\PermissionController;
use App\Http\Controllers\Admin\Users\RoleController;
use App\Http\Controllers\Admin\Users\UserController;
use App\Models\AdminMenu;
use App\Models\Category;
use App\Models\PublicMenu;
use Illuminate\Support\Facades\Route;

/*
| Admin routes. Mounted under the "admin" prefix with the "admin." name prefix
| by bootstrap/app.php, so route names read as admin.news.index and so on.
*/

/**
 * Resolves {menu} against the tree the route belongs to.
 *
 * App\Models\Menu is abstract, so implicit binding cannot instantiate it. Doing
 * it here also scopes the lookup: an admin-menu id is a 404 under the
 * public-menu URLs, and the other way round.
 */
Route::bind('menu', function (string $value) {
    /** @var class-string<\App\Models\Menu> $model */
    $model = request()->route()->defaults['model'] ?? AdminMenu::class;

    return $model::findOrFail($value);
});

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    // Throttling is owned by LoginRequest so a locked-out administrator gets
    // a form-level message instead of a bare 429 page.
    Route::post('login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

/**
 * Registers the taxonomy screens for one module. The `type` default is what
 * binds these URLs to a single taxonomy, and it must be registered before the
 * module's own {model} routes so that "category" is not swallowed as an id.
 */
$categoryRoutes = function (string $type): void {
    Route::get('category/data', [CategoryController::class, 'data'])->defaults('type', $type)->name('category.data');
    Route::get('category', [CategoryController::class, 'index'])->defaults('type', $type)->name('category.index');
    Route::get('category/create', [CategoryController::class, 'create'])->defaults('type', $type)->name('category.create');
    Route::post('category', [CategoryController::class, 'store'])->defaults('type', $type)->name('category.store');
    Route::get('category/{category}/edit', [CategoryController::class, 'edit'])->defaults('type', $type)->name('category.edit');
    Route::put('category/{category}', [CategoryController::class, 'update'])->defaults('type', $type)->name('category.update');
    Route::delete('category/{category}', [CategoryController::class, 'destroy'])->defaults('type', $type)->name('category.destroy');
};

Route::middleware(['auth', 'active'])->group(function () use ($categoryRoutes) {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/', fn () => redirect()->route('admin.dashboard'))->name('home');
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    /* ---------------------------------------------------------------- News */
    Route::prefix('news')->name('news.')->group(function () use ($categoryRoutes) {
        $categoryRoutes(Category::TYPE_NEWS);

        Route::get('tag/data', [TagController::class, 'data'])->name('tag.data');
        Route::get('tag', [TagController::class, 'index'])->name('tag.index');
        Route::post('tag', [TagController::class, 'store'])->name('tag.store');
        Route::get('tag/{tag}/edit', [TagController::class, 'edit'])->name('tag.edit');
        Route::put('tag/{tag}', [TagController::class, 'update'])->name('tag.update');
        Route::delete('tag/{tag}', [TagController::class, 'destroy'])->name('tag.destroy');

        Route::get('data', [NewsController::class, 'data'])->name('data');
        Route::patch('{news}/status', [NewsController::class, 'changeStatus'])->name('status');
    });

    Route::resource('news', NewsController::class);

    /* ------------------------------------------------------------ Services */
    Route::prefix('services')->name('services.')->group(function () use ($categoryRoutes) {
        $categoryRoutes(Category::TYPE_SERVICE);

        Route::get('data', [ServiceController::class, 'data'])->name('data');

        // Requirements / tariffs / steps hang off one service and are always
        // resolved through it, never by their own id alone.
        foreach ([
            'requirements' => RequirementController::class,
            'tariffs' => TariffController::class,
            'steps' => StepController::class,
        ] as $segment => $controller) {
            Route::prefix('{service}/'.$segment)->name($segment.'.')->group(function () use ($controller) {
                Route::get('/', [$controller, 'index'])->name('index');
                Route::post('/', [$controller, 'store'])->name('store');
                Route::get('{id}/edit', [$controller, 'edit'])->name('edit');
                Route::put('{id}', [$controller, 'update'])->name('update');
                Route::delete('{id}', [$controller, 'destroy'])->name('destroy');
            });
        }
    });

    Route::resource('services', ServiceController::class);

    /* ----------------------------------------------------------- Documents */
    Route::prefix('documents')->name('documents.')->group(function () use ($categoryRoutes) {
        $categoryRoutes(Category::TYPE_DOCUMENT);

        Route::get('data', [DocumentController::class, 'data'])->name('data');
    });

    Route::resource('documents', DocumentController::class)->except('show');

    /* ------------------------------------------------------------ Settings */
    Route::prefix('settings')->name('settings.')->group(function () {
        // Every key-value screen shares one controller; which screen it is
        // comes from the `group` route default.
        foreach (['general', 'seo', 'appearance', 'accessibility', 'footer'] as $group) {
            Route::get($group, [SettingsController::class, 'edit'])->defaults('group', $group)->name($group.'.edit');
            Route::put($group, [SettingsController::class, 'update'])->defaults('group', $group)->name($group.'.update');
        }

        Route::post('homepage/reorder', [HomepageController::class, 'reorder'])->name('homepage.reorder');
        Route::resource('homepage', HomepageController::class)->except('show');

        // Registered before the resource so "data" and "preview" are never
        // swallowed as a {carousel} id.
        Route::get('carousel/data', [CarouselController::class, 'data'])->name('carousel.data');
        Route::get('carousel/preview', [CarouselController::class, 'preview'])->name('carousel.preview');
        Route::post('carousel/reorder', [CarouselController::class, 'reorder'])->name('carousel.reorder');
        Route::post('carousel/{carousel}/duplicate', [CarouselController::class, 'duplicate'])->name('carousel.duplicate');
        Route::patch('carousel/{carousel}/toggle', [CarouselController::class, 'toggle'])->name('carousel.toggle');
        Route::resource('carousel', CarouselController::class)->except('show');

        Route::get('social', [SocialLinkController::class, 'index'])->name('social.index');
        Route::post('social', [SocialLinkController::class, 'store'])->name('social.store');
        Route::get('social/{social}/edit', [SocialLinkController::class, 'edit'])->name('social.edit');
        Route::put('social/{social}', [SocialLinkController::class, 'update'])->name('social.update');
        Route::delete('social/{social}', [SocialLinkController::class, 'destroy'])->name('social.destroy');
    });

    /* --------------------------------------------------------- Announcements */
    // Registered before the resource so "data" and "preview" are never
    // swallowed as an {announcement} id.
    Route::get('announcements/data', [AnnouncementController::class, 'data'])->name('announcements.data');
    Route::get('announcements/preview', [AnnouncementController::class, 'preview'])->name('announcements.preview');
    Route::post('announcements/reorder', [AnnouncementController::class, 'reorder'])->name('announcements.reorder');
    Route::patch('announcements/{announcement}/toggle', [AnnouncementController::class, 'toggle'])->name('announcements.toggle');
    Route::resource('announcements', AnnouncementController::class)->except('show');

    /* -------------------------------------------------- Unggahan media sosial */
    Route::get('social-posts/data', [SocialPostController::class, 'data'])->name('social-posts.data');
    Route::post('social-posts/reorder', [SocialPostController::class, 'reorder'])->name('social-posts.reorder');
    Route::post('social-posts/fetch', [SocialPostController::class, 'fetch'])->name('social-posts.fetch');
    Route::post('social-posts/sync', [SocialPostController::class, 'syncAll'])->name('social-posts.sync-all');
    Route::post('social-posts/{social_post}/sync', [SocialPostController::class, 'sync'])->name('social-posts.sync');
    Route::patch('social-posts/{social_post}/toggle', [SocialPostController::class, 'toggle'])->name('social-posts.toggle');
    Route::resource('social-posts', SocialPostController::class)
        ->parameters(['social-posts' => 'social_post'])
        ->except('show');

    /* ------------------------------------------------- Pengaduan & chatbot */
    // Jenis pengaduan beserta syarat buktinya. Nama rutenya sengaja tidak
    // berada di bawah "complaints/" agar tidak pernah tertelan oleh
    // complaints/{complaint} yang menerima sembarang segmen.
    Route::resource('complaint-categories', ComplaintCategoryController::class)
        ->parameters(['complaint-categories' => 'category'])
        ->except('show');

    // Instansi tujuan penerusan, dipakai triase petugas dari chat.
    Route::resource('dispositions', DispositionTargetController::class)
        ->parameters(['dispositions' => 'target'])
        ->except('show');

    Route::get('complaints/data', [ComplaintController::class, 'data'])->name('complaints.data');
    // Attachments are served here rather than from a public disk: they are
    // photographs of somebody's street, sent privately.
    Route::get('complaints/attachment/{attachment}', [ComplaintController::class, 'attachment'])->name('complaints.attachment');
    Route::get('complaints', [ComplaintController::class, 'index'])->name('complaints.index');
    Route::get('complaints-peta', [ComplaintController::class, 'map'])->name('complaints.map');

    // Questions people keep asking the bot, and turning one into an FAQ.
    Route::get('bot/pertanyaan', [BotQuestionController::class, 'index'])->name('bot.questions.index');
    Route::post('bot/pertanyaan/jadikan-faq', [BotQuestionController::class, 'promote'])->name('bot.questions.promote');
    Route::post('bot/pertanyaan/singkirkan', [BotQuestionController::class, 'ignore'])->name('bot.questions.ignore');
    Route::delete('bot/pertanyaan/{topic}', [BotQuestionController::class, 'restore'])->name('bot.questions.restore');
    // Tiles pass through this server so an operator's browser never calls a
    // map provider. Constrained to numbers: these become a URL and a path.
    Route::get('peta/petak/{z}/{x}/{y}', MapTileController::class)
        ->whereNumber(['z', 'x', 'y'])
        ->name('map.tile');
    Route::get('complaints/{complaint}', [ComplaintController::class, 'show'])->name('complaints.show');
    Route::put('complaints/{complaint}', [ComplaintController::class, 'update'])->name('complaints.update');
    Route::post('complaints/{complaint}/reply', [ComplaintController::class, 'reply'])->name('complaints.reply');
    Route::delete('complaints/{complaint}', [ComplaintController::class, 'destroy'])->name('complaints.destroy');

    Route::prefix('bot')->name('bot.')->group(function () {
        Route::get('flows', [BotFlowController::class, 'index'])->name('flows.index');
        Route::post('flows', [BotFlowController::class, 'store'])->name('flows.store');
        Route::get('flows/{flow}/edit', [BotFlowController::class, 'edit'])->name('flows.edit');
        Route::put('flows/{flow}/graph', [BotFlowController::class, 'save'])->name('flows.save');
        Route::post('flows/{flow}/publish', [BotFlowController::class, 'publish'])->name('flows.publish');
        Route::post('flows/{flow}/default', [BotFlowController::class, 'makeDefault'])->name('flows.default');
        Route::delete('flows/{flow}', [BotFlowController::class, 'destroy'])->name('flows.destroy');

        Route::get('ai', [AiSettingsController::class, 'edit'])->name('ai.edit');
        Route::put('ai', [AiSettingsController::class, 'update'])->name('ai.update');
        Route::post('ai/forget', [AiSettingsController::class, 'forget'])->name('ai.forget');
        Route::post('ai/test', [AiSettingsController::class, 'test'])->name('ai.test');

        Route::get('channels', [BotChannelController::class, 'index'])->name('channels.index');
        Route::put('channels/{channel}', [BotChannelController::class, 'update'])->name('channels.update');
        Route::post('channels/{channel}/forget', [BotChannelController::class, 'forget'])->name('channels.forget');
        Route::post('channels/{channel}/test', [BotChannelController::class, 'test'])->name('channels.test');

        Route::post('data-sources/preview', [BotDataSourceController::class, 'preview'])->name('data-sources.preview');
        Route::resource('data-sources', BotDataSourceController::class)
            ->parameters(['data-sources' => 'dataSource'])
            ->except('show');

        // Siapa yang dikabari saat pengaduan baru masuk, per kategori.
        Route::resource('recipients', BotRecipientController::class)->except('show');

        // Mencoba percakapan dari panel, lewat mesin yang sama dengan aslinya.
        Route::get('simulator', [BotSimulatorController::class, 'index'])->name('simulator.index');
        Route::post('simulator', [BotSimulatorController::class, 'send'])->name('simulator.send');
        Route::delete('simulator', [BotSimulatorController::class, 'reset'])->name('simulator.reset');

        Route::get('conversations/data', [BotConversationController::class, 'data'])->name('conversations.data');
        Route::get('conversations', [BotConversationController::class, 'index'])->name('conversations.index');
        Route::get('conversations/{conversation}', [BotConversationController::class, 'show'])->name('conversations.show');
        Route::get('media/{message}', [BotConversationController::class, 'media'])->name('media');
        Route::get('avatar/{contact}', [BotConversationController::class, 'avatar'])->name('avatar');
    });

    /* ------------------------------------------------------- Users & access */
    Route::get('users/data', [UserController::class, 'data'])->name('users.data');
    Route::resource('users', UserController::class)->except('show');

    Route::get('roles/data', [RoleController::class, 'data'])->name('roles.data');
    Route::resource('roles', RoleController::class)->except('show');

    Route::get('permissions', [PermissionController::class, 'index'])->name('permissions.index');

    /* --------------------------------------------------------------- Pages */
    Route::get('pages/data', [PageController::class, 'data'])->name('pages.data');
    Route::resource('pages', PageController::class)->except('show');

    /* --------------------------------------------------------------- Menus */
    // Both trees share one controller; the route names which model it edits.
    foreach (['admin' => AdminMenu::class, 'public' => PublicMenu::class] as $kind => $model) {
        // `defaults()` is a Route method, not a registrar one, so it is applied
        // per route rather than to the group.
        Route::prefix('menus/'.$kind)->name('menus.'.$kind.'.')->group(function () use ($model) {
            Route::get('/', [MenuController::class, 'index'])->defaults('model', $model)->name('index');
            Route::get('create', [MenuController::class, 'create'])->defaults('model', $model)->name('create');
            Route::post('/', [MenuController::class, 'store'])->defaults('model', $model)->name('store');
            Route::post('reorder', [MenuController::class, 'reorder'])->defaults('model', $model)->name('reorder');
            Route::get('{menu}/edit', [MenuController::class, 'edit'])->defaults('model', $model)->name('edit');
            Route::put('{menu}', [MenuController::class, 'update'])->defaults('model', $model)->name('update');
            Route::delete('{menu}', [MenuController::class, 'destroy'])->defaults('model', $model)->name('destroy');
        });
    }

    /* ----------------------------------------------------------- Audit log */
    Route::prefix('audit-logs')->name('audit-logs.')->group(function () {
        Route::get('data', [AuditLogController::class, 'data'])->name('data');
        Route::get('/', [AuditLogController::class, 'index'])->name('index');
        Route::post('clear-cache', [AuditLogController::class, 'clearCache'])->name('clear-cache');
        Route::get('{auditLog}', [AuditLogController::class, 'show'])->name('show');
    });

    // Icon artwork for the picker preview. Read-only, name-checked against the
    // catalogue, so it cannot be used to reach anything else.
    Route::get('icons/{name}', IconController::class)
        ->where('name', '[a-z0-9-]+')
        ->name('icons.show');

    /* --------------------------------------------------------------- Media */
    Route::get('media', [MediaController::class, 'index'])->name('media.index');
    Route::post('media', [MediaController::class, 'store'])->name('media.store');
    Route::delete('media/{media}', [MediaController::class, 'destroy'])->name('media.destroy');

    /* ----------------------------------------------------------------- FAQ */
    Route::prefix('faq')->name('faq.')->group(function () use ($categoryRoutes) {
        $categoryRoutes(Category::TYPE_FAQ);

        Route::get('data', [FaqController::class, 'data'])->name('data');
    });

    Route::resource('faq', FaqController::class)->except('show')->parameters(['faq' => 'faq']);
});

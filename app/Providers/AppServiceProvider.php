<?php

namespace App\Providers;

use App\Services\Captcha\CaptchaServiceInterface;
use App\Services\Captcha\MathCaptchaService;
use App\Services\Captcha\NullCaptchaService;
use App\Services\Captcha\TurnstileCaptchaService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use App\Models\User;
use App\Policies\RolePolicy;
use App\Support\StructuralGuards;
use App\Services\Menu\MenuService;
use App\Services\Theme\ThemeService;
use App\Services\Public\SiteContext;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CaptchaServiceInterface::class, function () {
            $driver = config('captcha.driver', 'null');

            return match ($driver) {
                'turnstile' => new TurnstileCaptchaService(
                    config('captcha.turnstile.site_key'),
                    config('captcha.turnstile.secret_key'),
                    config('captcha.turnstile.verify_url'),
                    (int) config('captcha.turnstile.timeout', 5),
                ),
                'math' => new MathCaptchaService($this->app['session.store']),
                default => new NullCaptchaService(),
            };
        });
    }

    public function boot(): void
    {
        // Relations are resolved in bulk instead of per-row, which keeps the
        // menu/News listings free of N+1 queries without scattering with() calls.
        Model::automaticallyEagerLoadRelationships();
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        $this->configureAuthorization();
        $this->configureRateLimiting();
        $this->configureViews();

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }

    private function configureAuthorization(): void
    {
        // Super Admin is defined by role, not by an ever-growing grant list, so
        // a module added later is covered without re-seeding permissions.
        //
        // The bypass is not unconditional: the cases listed in StructuralGuards
        // fall through to the ordinary policy, which denies them for everyone.
        Gate::before(function (User $user, string $ability, array $arguments = []) {
            if (! $user->hasRole('Super Admin')) {
                return null;
            }

            return StructuralGuards::applyTo($user, $ability, $arguments) ? null : true;
        });

        // Spatie's Role lives outside App\Models, so policy auto-discovery
        // cannot find it.
        Gate::policy(\Spatie\Permission\Models\Role::class, RolePolicy::class);
    }

    private function configureViews(): void
    {
        // The sidebar and the login challenge are needed by their views on every
        // render; composing them here keeps controllers free of that plumbing.
        View::composer('components.layouts.admin', function ($view) {
            $view->with([
                'adminMenu' => app(MenuService::class)->adminTree(auth()->user()),
                // The panel's own identity is a setting like everything else,
                // not APP_NAME from a file nobody with a login can edit.
                'site' => app(SiteContext::class),
            ]);
        });

        View::composer('admin.auth.login', function ($view) {
            $view->with([
                'captcha' => app(CaptchaServiceInterface::class),
                'siteName' => app(SiteContext::class)->siteName(),
            ]);
        });

        // The public layout and everything it pulls in (header, footer, SEO
        // tags, accessibility panel) share one context object, so a controller
        // never has to assemble site-wide data itself.
        View::composer(['components.layouts.public', 'public.*', 'components.public.*', 'components.seo.*'], function ($view) {
            $site = app(SiteContext::class);

            $view->with([
                'site' => $site,
                'publicMenu' => $site->menu(),
                'accessibility' => $site->accessibility(),
                'theme' => app(ThemeService::class),
            ]);
        });
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));

        RateLimiter::for('search', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));

        // Downloads stream files from disk, so they are capped separately and
        // more tightly than ordinary page views.
        RateLimiter::for('downloads', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));
    }
}


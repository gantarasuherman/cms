<?php

namespace App\Http\Middleware;

use App\Models\PageView;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records a page view for the public site.
 *
 * Two rules shape this class:
 *
 *  - **It records nothing that identifies a person.** The IP and user agent are
 *    hashed together with the date and the app key and then discarded; what is
 *    stored cannot be reversed, and rotates at midnight so it cannot follow a
 *    visitor across days.
 *  - **It must never break a page.** Statistics are not worth a 500, so the
 *    write runs after the response is prepared and any failure is logged and
 *    swallowed.
 */
class RecordPageView
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->shouldRecord($request, $response)) {
            $this->record($request);
        }

        return $response;
    }

    private function shouldRecord(Request $request, Response $response): bool
    {
        return config('analytics.enabled', true)
            && $request->isMethod('GET')
            && ! $request->ajax()
            && $response->getStatusCode() === 200
            // A redirect or an error page is not a visit worth counting.
            && ! $request->is(...config('analytics.ignore_paths', []))
            && ! $this->isBot($request);
    }

    private function isBot(Request $request): bool
    {
        $agent = Str::lower((string) $request->userAgent());

        if ($agent === '') {
            return true;
        }

        foreach (config('analytics.bot_signatures', []) as $signature) {
            if (str_contains($agent, $signature)) {
                return true;
            }
        }

        return false;
    }

    private function record(Request $request): void
    {
        try {
            $now = now();

            PageView::create([
                'path' => Str::limit($request->path(), 500, ''),
                'route_name' => $request->route()?->getName(),
                'visitor_hash' => $this->visitorHash($request, $now->toDateString()),
                'referrer_host' => $this->referrerHost($request),
                'viewed_on' => $now->toDateString(),
                'viewed_at' => $now,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat kunjungan halaman.', ['message' => $e->getMessage()]);
        }
    }

    /**
     * One-way, salted with the app key and the current date.
     *
     * The date in the input is what makes this pseudonymous rather than a
     * persistent identifier: yesterday's hash for the same visitor is a
     * different value, so the table cannot be used to build a visit history.
     */
    private function visitorHash(Request $request, string $date): string
    {
        return hash('sha256', implode('|', [
            $request->ip(),
            (string) $request->userAgent(),
            $date,
            config('app.key'),
        ]));
    }

    /** Only the host is kept — a full referrer URL can carry search terms. */
    private function referrerHost(Request $request): ?string
    {
        $referrer = $request->headers->get('referer');

        if (blank($referrer)) {
            return null;
        }

        $host = parse_url($referrer, PHP_URL_HOST);

        if (! is_string($host) || $host === $request->getHost()) {
            return null;
        }

        return Str::limit($host, 250, '');
    }
}


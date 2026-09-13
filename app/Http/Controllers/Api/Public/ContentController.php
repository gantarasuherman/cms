<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\CarouselResource;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\DocumentResource;
use App\Http\Resources\FaqResource;
use App\Http\Resources\MenuResource;
use App\Http\Resources\NewsResource;
use App\Http\Resources\PageResource;
use App\Http\Resources\ServiceResource;
use App\Http\Responses\ApiResponse;
use App\Services\Public\DocumentReader;
use App\Services\Public\FaqReader;
use App\Services\Public\HomepageReader;
use App\Services\Public\NewsReader;
use App\Services\Public\PageReader;
use App\Services\Public\ServiceReader;
use App\Services\Public\SiteContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The public, read-only API.
 *
 * It reads through the same services as the Blade site, so the two can never
 * disagree about what is published. No endpoint here writes anything: there is
 * no mutation to authorise, and nothing unpublished is reachable.
 */
class ContentController extends Controller
{
    public function __construct(
        private readonly NewsReader $news,
        private readonly ServiceReader $services,
        private readonly DocumentReader $documents,
        private readonly FaqReader $faqs,
        private readonly PageReader $pages,
        private readonly SiteContext $site,
    ) {
    }

    public function home(HomepageReader $homepage): JsonResponse
    {
        return ApiResponse::success([
            'carousel' => CarouselResource::collection($homepage->slides()),
            'featured_news' => NewsResource::collection($this->news->featured()),
            'latest_news' => NewsResource::collection($this->news->latest()),
            'services' => ServiceResource::collection($this->services->highlighted()),
            'documents' => DocumentResource::collection($this->documents->latest()),
            'faqs' => FaqResource::collection($this->faqs->highlighted()),
        ]);
    }

    public function menus(): JsonResponse
    {
        return ApiResponse::success(MenuResource::collection($this->site->menu()));
    }

    public function settings(): JsonResponse
    {
        $general = $this->site->general();

        // Only the public-facing identity is exposed. Nothing from the other
        // settings groups leaks through this endpoint.
        return ApiResponse::success([
            'site_name' => $general['site_name'] ?? null,
            'site_description' => $general['site_description'] ?? null,
            'logo' => ($general['logo'] ?? null) ? \Storage::disk('public')->url($general['logo']) : null,
            'email' => $general['email'] ?? null,
            'phone' => $general['phone'] ?? null,
            'address' => $general['address'] ?? null,
            'copyright' => $general['copyright'] ?? null,
            'social_links' => $this->site->socialLinks()->map(fn ($link) => [
                'platform' => $link->platform,
                'label' => $link->label,
                'icon' => $link->icon,
                'url' => $link->url,
            ])->values(),
            'accessibility' => $this->site->accessibility(),
        ]);
    }

    public function carousel(HomepageReader $homepage): JsonResponse
    {
        return ApiResponse::success(CarouselResource::collection($homepage->slides()));
    }

    public function newsIndex(Request $request): JsonResponse
    {
        $filters = $this->filters($request);

        $paginator = $this->news->paginate($filters['kategori'], $filters['q'], $filters['per_page']);

        return ApiResponse::paginated(NewsResource::collection($paginator), $paginator);
    }

    public function newsShow(string $slug): JsonResponse
    {
        $news = $this->news->findBySlug($slug);

        if ($news === null) {
            return ApiResponse::notFound('Berita tidak ditemukan');
        }

        return ApiResponse::success(new NewsResource($news));
    }

    public function newsCategories(): JsonResponse
    {
        return ApiResponse::success(CategoryResource::collection($this->news->categories()));
    }

    public function servicesIndex(Request $request): JsonResponse
    {
        $filters = $this->filters($request);

        $paginator = $this->services->paginate($filters['kategori'], $filters['q'], $filters['per_page']);

        return ApiResponse::paginated(ServiceResource::collection($paginator), $paginator);
    }

    public function servicesShow(string $slug): JsonResponse
    {
        $service = $this->services->findBySlug($slug);

        if ($service === null) {
            return ApiResponse::notFound('Layanan tidak ditemukan');
        }

        return ApiResponse::success(new ServiceResource($service));
    }

    public function serviceCategories(): JsonResponse
    {
        return ApiResponse::success(CategoryResource::collection($this->services->categories()));
    }

    public function documentsIndex(Request $request): JsonResponse
    {
        $filters = $this->filters($request);

        $paginator = $this->documents->paginate($filters['kategori'], $filters['q'], $filters['per_page']);

        return ApiResponse::paginated(DocumentResource::collection($paginator), $paginator);
    }

    public function documentsShow(string $slug): JsonResponse
    {
        $document = $this->documents->findBySlug($slug);

        if ($document === null) {
            return ApiResponse::notFound('Dokumen tidak ditemukan');
        }

        return ApiResponse::success(new DocumentResource($document));
    }

    public function faqs(Request $request): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::success(
            FaqResource::collection($this->faqs->all($filters['kategori'], $filters['q'])),
        );
    }

    public function page(string $slug): JsonResponse
    {
        $page = $this->pages->findBySlug($slug);

        if ($page === null) {
            return ApiResponse::notFound('Halaman tidak ditemukan');
        }

        return ApiResponse::success(new PageResource($page));
    }

    /**
     * Query parameters are validated and the page size is capped, so a caller
     * cannot ask for the entire table in one request.
     *
     * @return array{kategori: ?string, q: ?string, per_page: int}
     */
    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'kategori' => ['nullable', 'string', 'max:120'],
            'q' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        return [
            'kategori' => $validated['kategori'] ?? null,
            'q' => $validated['q'] ?? null,
            'per_page' => (int) ($validated['per_page'] ?? 12),
        ];
    }
}


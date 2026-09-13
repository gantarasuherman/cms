<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\SocialLink;
use App\Services\Audit\AuditLogger;
use App\Services\Cache\PublicCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Social platforms are rows, not an enum: an institution can add whichever
 * network it uses without a code change.
 */
class SocialLinkController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly PublicCache $cache,
    ) {
    }

    public function index(): View
    {
        $this->authorize('viewAny', SocialLink::class);

        return view('admin.settings.social.index', [
            'links' => SocialLink::orderBy('sort_order')->get(),
            'link' => new SocialLink(['is_active' => true]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', SocialLink::class);

        $link = SocialLink::create($this->validated($request));

        $this->audit->recordModel('create', 'social_link', $link);
        $this->cache->forget(PublicCache::SOCIAL);

        return back()->with('success', 'Tautan berhasil ditambahkan.');
    }

    public function edit(SocialLink $social): View
    {
        $this->authorize('update', $social);

        return view('admin.settings.social.edit', ['link' => $social]);
    }

    public function update(Request $request, SocialLink $social): RedirectResponse
    {
        $this->authorize('update', $social);

        $original = $social->getOriginal();
        $social->update($this->validated($request));

        $this->audit->recordModel('update', 'social_link', $social, $original);
        $this->cache->forget(PublicCache::SOCIAL);

        return redirect()->route('admin.settings.social.index')->with('success', 'Tautan berhasil diperbarui.');
    }

    public function destroy(SocialLink $social): RedirectResponse
    {
        $this->authorize('delete', $social);

        $social->delete();

        $this->audit->record('delete', 'social_link', $social->getKey());
        $this->cache->forget(PublicCache::SOCIAL);

        return back()->with('success', 'Tautan berhasil dihapus.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'platform' => ['required', 'string', 'max:64'],
            'label' => ['nullable', 'string', 'max:120'],
            'icon' => ['nullable', 'string', 'max:64', Rule::exists('icons', 'name')->where('is_active', true)],
            // Only http(s): a javascript: or data: URL here would become a
            // stored XSS vector on every public page that renders the footer.
            'url' => ['required', 'url', 'starts_with:https://,http://', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'url.starts_with' => 'Alamat harus dimulai dengan http:// atau https://.',
        ]);
    }
}


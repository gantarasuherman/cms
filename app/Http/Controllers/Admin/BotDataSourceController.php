<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bot\BotDataSource;
use App\Models\Bot\BotNode;
use App\Services\Audit\AuditLogger;
use App\Services\Bot\ContentLister;
use App\Support\BotNodes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Where the bot reads its answers from.
 *
 * A source binds a published part of the site — news, services, documents,
 * FAQs — to the two lines of wording that turn a row into a chat message. The
 * *which* is a fixed vocabulary in code; the *wording* is entirely an
 * administrator's, which is the point.
 */
class BotDataSourceController extends Controller
{
    public function __construct(
        private readonly ContentLister $lister,
        private readonly AuditLogger $audit,
    ) {
    }

    public function index(): View
    {
        $this->authorize('viewAny', BotDataSource::class);

        return view('admin.bot.data-sources.index', [
            'sources' => BotDataSource::orderBy('name')->get(),
            // Which flows would notice if one were removed.
            'usage' => BotNode::where('type', BotNodes::DATA_SOURCE)
                ->get()
                ->groupBy(fn (BotNode $node) => $node->setting('data_source'))
                ->map->count(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', BotDataSource::class);

        return $this->form(new BotDataSource([
            'limit' => 5,
            'is_active' => true,
            'list_template' => '{index}. {title}',
            'detail_template' => "*{title}*\n\n{excerpt}\n\nSelengkapnya: {url}",
        ]));
    }

    public function edit(BotDataSource $dataSource): View
    {
        $this->authorize('update', $dataSource);

        return $this->form($dataSource);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', BotDataSource::class);

        $data = $this->validated($request);

        $source = BotDataSource::create($data + [
            'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(4)),
        ]);

        $this->audit->recordModel('create', 'bot_data_source', $source);

        return redirect()->route('admin.bot.data-sources.index')->with('success', 'Sumber data ditambahkan.');
    }

    public function update(Request $request, BotDataSource $dataSource): RedirectResponse
    {
        $this->authorize('update', $dataSource);

        $original = $dataSource->getOriginal();
        $dataSource->update($this->validated($request));

        $this->audit->recordModel('update', 'bot_data_source', $dataSource, $original);

        return redirect()->route('admin.bot.data-sources.index')->with('success', 'Sumber data diperbarui.');
    }

    public function destroy(BotDataSource $dataSource): RedirectResponse
    {
        $this->authorize('delete', $dataSource);

        $used = BotNode::where('type', BotNodes::DATA_SOURCE)
            ->get()
            ->filter(fn (BotNode $node) => $node->setting('data_source') === $dataSource->slug);

        if ($used->isNotEmpty()) {
            // Deleting it would leave those nodes answering "sumber informasi
            // belum disiapkan" to whoever reached them.
            return back()->with('warning', 'Masih dipakai '.$used->count().' node pada alur percakapan. Lepaskan dulu dari alur tersebut.');
        }

        $dataSource->delete();
        $this->audit->record('delete', 'bot_data_source', $dataSource->getKey());

        return redirect()->route('admin.bot.data-sources.index')->with('success', 'Sumber data dihapus.');
    }

    /**
     * The reply as a person would receive it.
     *
     * Rendered from the real content through the same lister the bot uses, so
     * what is shown here is what will actually be sent — a preview built from
     * a different code path is a preview that can lie.
     */
    public function preview(Request $request): JsonResponse
    {
        $this->authorize('viewAny', BotDataSource::class);

        $data = $request->validate([
            // The name leads the reply, so a preview without it gets its very
            // first line wrong.
            'name' => ['nullable', 'string', 'max:120'],
            'source' => ['required', 'string', Rule::in(array_keys(BotNodes::dataSources()))],
            'limit' => ['required', 'integer', 'between:1,20'],
            'list_template' => ['nullable', 'string', 'max:255'],
            'detail_template' => ['nullable', 'string', 'max:2000'],
        ]);

        $source = new BotDataSource($data + ['name' => 'Pratinjau']);
        $source->name = filled($data['name'] ?? null) ? $data['name'] : 'Pratinjau';
        $items = $this->lister->items($source);

        if ($items === []) {
            return response()->json([
                'empty' => true,
                'list' => 'Belum ada isi yang dapat ditampilkan dari sumber ini.',
                'detail' => '',
            ]);
        }

        return response()->json([
            'empty' => false,
            'list' => $source->name."\n\n".$this->lister->list($source, $items)
                ."\n\nBalas nomornya untuk melihat rincian, atau 0 untuk kembali.",
            'detail' => $this->lister->detail($source, $items[0], 1),
        ]);
    }

    private function form(BotDataSource $source): View
    {
        return view('admin.bot.data-sources.form', [
            'source' => $source,
            'sources' => BotNodes::dataSources(),
        ]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            // A fixed vocabulary: a free-text model name here would be a
            // ready-made way to read the users table over WhatsApp.
            'source' => ['required', 'string', Rule::in(array_keys(BotNodes::dataSources()))],
            'limit' => ['required', 'integer', 'between:1,20'],
            'list_template' => ['nullable', 'string', 'max:255'],
            'detail_template' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ]) + ['is_active' => $request->boolean('is_active')];
    }
}

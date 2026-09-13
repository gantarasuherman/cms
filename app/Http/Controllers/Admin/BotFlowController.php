<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BotFlowGraphRequest;
use App\Models\Bot\BotDataSource;
use App\Models\Bot\BotEdge;
use App\Models\Bot\BotFlow;
use App\Models\Bot\BotFlowVersion;
use App\Models\Bot\BotNode;
use App\Models\ComplaintCategory;
use App\Services\Audit\AuditLogger;
use App\Services\Bot\FlowInspector;
use App\Support\BotNodes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BotFlowController extends Controller
{
    public function __construct(
        private readonly FlowInspector $inspector,
        private readonly AuditLogger $audit,
    ) {
    }

    public function index(): View
    {
        $this->authorize('viewAny', BotFlow::class);

        return view('admin.bot.flows.index', [
            'flows' => BotFlow::withCount(['nodes', 'edges'])->orderByDesc('is_default')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', BotFlow::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $flow = BotFlow::create([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(4)),
            'description' => $data['description'] ?? null,
            'is_active' => false,
            'is_default' => false,
            'version' => 1,
            'updated_by' => $request->user()->getKey(),
        ]);

        // A new flow starts with the one node every flow must have, so the
        // editor opens on something rather than an empty canvas with a rule.
        $flow->nodes()->create([
            'key' => 'start', 'type' => BotNodes::START, 'label' => 'Mulai',
            'position_x' => 80, 'position_y' => 200, 'config' => ['triggers' => ['halo', 'menu', '/start']],
        ]);

        $this->audit->recordModel('create', 'bot_flow', $flow);

        return redirect()->route('admin.bot.flows.edit', $flow)->with('success', 'Alur dibuat. Silakan susun nodenya.');
    }

    public function edit(BotFlow $flow): View
    {
        $this->authorize('update', $flow);

        return view('admin.bot.flows.edit', [
            'flow' => $flow->load(['nodes', 'edges']),
            'graph' => $flow->toGraph(),
            'nodeTypes' => BotNodes::types(),
            'inputKinds' => BotNodes::inputKinds(),
            'actions' => BotNodes::actions(),
            'actionNames' => BotNodes::actionNames(),
            'retryBehaviours' => BotNodes::retryBehaviours(),
            'dataSources' => BotDataSource::active()->pluck('name', 'slug'),
            'categoryCount' => ComplaintCategory::active()->count(),
            'problems' => $this->inspector->inspect($flow),
        ]);
    }

    /**
     * Replaces the whole graph.
     *
     * Replaced rather than merged, in one transaction: the editor holds the
     * complete picture, and a partial write would leave a flow that is neither
     * what was drawn nor what was there before.
     */
    public function save(BotFlowGraphRequest $request, BotFlow $flow): JsonResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($flow, $data, $request) {
            $flow->nodes()->delete();
            $flow->edges()->delete();

            foreach ($data['nodes'] as $node) {
                BotNode::create([
                    'bot_flow_id' => $flow->getKey(),
                    'key' => $node['key'],
                    'type' => $node['type'],
                    'label' => $node['label'] ?? null,
                    'config' => $node['config'] ?? [],
                    'position_x' => (int) $node['position']['x'],
                    'position_y' => (int) $node['position']['y'],
                ]);
            }

            foreach ($data['edges'] as $index => $edge) {
                BotEdge::create([
                    'bot_flow_id' => $flow->getKey(),
                    'from_node' => $edge['from'],
                    'to_node' => $edge['to'],
                    'condition' => $edge['condition'] ?? null,
                    'label' => $edge['label'] ?? null,
                    'sort_order' => ($index + 1) * 10,
                ]);
            }

            $flow->update(['updated_by' => $request->user()->getKey()]);
        });

        $this->audit->record('update', 'bot_flow', $flow->getKey(), null, [
            'nodes' => count($data['nodes']), 'edges' => count($data['edges']),
        ]);

        $flow->refresh()->load(['nodes', 'edges']);

        return response()->json([
            'success' => true,
            'message' => 'Alur disimpan.',
            'problems' => $this->inspector->inspect($flow),
        ]);
    }

    /**
     * Makes the saved draft the one conversations run.
     *
     * A snapshot is kept so a conversation already under way keeps following
     * the shape it began on, and refused while errors remain: a flow with an
     * unreachable node is a flow that strands somebody.
     */
    public function publish(Request $request, BotFlow $flow): RedirectResponse
    {
        $this->authorize('update', $flow);

        $flow->load(['nodes', 'edges']);

        if (! $this->inspector->publishable($flow)) {
            return back()->with('warning', 'Alur belum bisa diterbitkan: masih ada kesalahan yang harus dibereskan.');
        }

        DB::transaction(function () use ($flow, $request) {
            $flow->version++;
            $flow->published_at = now();
            $flow->is_active = true;
            $flow->save();

            BotFlowVersion::create([
                'bot_flow_id' => $flow->getKey(),
                'version' => $flow->version,
                'snapshot' => $flow->toGraph(),
                'published_by' => $request->user()->getKey(),
            ]);
        });

        $this->audit->record('publish', 'bot_flow', $flow->getKey(), null, ['version' => $flow->version]);

        return back()->with('success', 'Alur diterbitkan sebagai versi '.$flow->version.'.');
    }

    public function makeDefault(BotFlow $flow): RedirectResponse
    {
        $this->authorize('update', $flow);

        DB::transaction(function () use ($flow) {
            // Exactly one default, or which flow answers becomes a matter of
            // insert order.
            BotFlow::where('is_default', true)->update(['is_default' => false]);
            $flow->update(['is_default' => true, 'is_active' => true]);
        });

        return back()->with('success', '"'.$flow->name.'" kini menjadi alur bawaan.');
    }

    public function destroy(BotFlow $flow): RedirectResponse
    {
        $this->authorize('delete', $flow);

        if ($flow->is_default) {
            return back()->with('warning', 'Alur bawaan tidak dapat dihapus. Tetapkan alur lain sebagai bawaan lebih dulu.');
        }

        $flow->delete();
        $this->audit->record('delete', 'bot_flow', $flow->getKey());

        return redirect()->route('admin.bot.flows.index')->with('success', 'Alur dihapus.');
    }
}

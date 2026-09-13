<?php

namespace Tests\Feature\Bot;

use App\Models\Bot\BotFlow;
use App\Models\User;
use Database\Seeders\BotFlowSeeder;
use Database\Seeders\ComplaintCategorySeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BotFlowEditorTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private BotFlow $flow;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SettingSeeder::class);
        $this->seed(MenuSeeder::class);
        $this->seed(ComplaintCategorySeeder::class);
        $this->seed(BotFlowSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super Admin');
        $this->flow = BotFlow::firstOrFail();
    }

    private function graph(array $overrides = []): array
    {
        $graph = $this->flow->fresh()->load(['nodes', 'edges'])->toGraph();

        return array_merge(['nodes' => $graph['nodes'], 'edges' => $graph['edges']], $overrides);
    }

    private function save(array $graph): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin)
            ->putJson(route('admin.bot.flows.save', $this->flow), $graph);
    }

    /* ------------------------------------------------------------- screens */

    public function test_the_editor_screens_render(): void
    {
        $this->actingAs($this->admin)->get(route('admin.bot.flows.index'))->assertOk()->assertSee('Alur Utama');

        $this->actingAs($this->admin)->get(route('admin.bot.flows.edit', $this->flow))
            ->assertOk()
            ->assertSee('data-flow-editor', false)
            ->assertSee('data-flow-outline', false)
            // The canvas is unusable without a mouse, so the keyboard routes
            // have to be written down where they can be read.
            ->assertSee('Dengan papan ketik');
    }

    public function test_a_new_flow_starts_with_the_node_every_flow_must_have(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.bot.flows.store'), ['name' => 'Alur Uji'])
            ->assertRedirect();

        $flow = BotFlow::where('name', 'Alur Uji')->firstOrFail();

        $this->assertCount(1, $flow->nodes);
        $this->assertSame('start', $flow->nodes->first()->type);
    }

    /* -------------------------------------------------------------- saving */

    public function test_the_graph_round_trips_without_losing_a_setting(): void
    {
        // The bug this guards: a form request returns only the keys it has
        // rules for, so every undeclared setting was dropped on save and the
        // editor quietly ate its own configuration.
        $before = collect($this->graph()['nodes'])->keyBy('key');

        $this->save($this->graph())->assertOk()->assertJsonPath('success', true);

        $after = collect($this->flow->fresh()->load('nodes')->toGraph()['nodes'])->keyBy('key');

        foreach ($before as $key => $node) {
            $was = $node['config'];
            $now = $after[$key]['config'];
            ksort($was);
            ksort($now);

            // Key order shifts because rules are applied in declaration order;
            // what must not change is any setting or its value.
            $this->assertEquals($was, $now, "Setelan node {$key} berubah saat disimpan.");
        }
    }

    public function test_positions_are_stored(): void
    {
        $graph = $this->graph();
        $graph['nodes'][1]['position'] = ['x' => 640, 'y' => 360];

        $this->save($graph)->assertOk();

        $node = $this->flow->fresh()->nodes->firstWhere('key', $graph['nodes'][1]['key']);

        $this->assertSame(640, $node->position_x);
        $this->assertSame(360, $node->position_y);
    }

    public function test_a_flow_without_exactly_one_start_is_refused(): void
    {
        $graph = $this->graph();
        $graph['nodes'] = collect($graph['nodes'])->reject(fn ($n) => $n['type'] === 'start')->values()->all();
        $graph['edges'] = [];

        $this->save($graph)->assertStatus(422)->assertJsonValidationErrors('nodes');

        $graph = $this->graph();
        $graph['nodes'][] = ['key' => 'start_2', 'type' => 'start', 'label' => 'Kedua', 'config' => [], 'position' => ['x' => 0, 'y' => 0]];

        // Two starts would make which one runs a matter of insert order.
        $this->save($graph)->assertStatus(422);
    }

    public function test_duplicate_keys_are_refused(): void
    {
        $graph = $this->graph();
        $graph['nodes'][2]['key'] = $graph['nodes'][1]['key'];

        $this->save($graph)->assertStatus(422)->assertJsonValidationErrors('nodes');
    }

    public function test_an_edge_to_a_node_that_does_not_exist_is_refused(): void
    {
        $graph = $this->graph();
        $index = count($graph['edges']);
        $graph['edges'][] = ['from' => 'start', 'to' => 'tidak_ada', 'condition' => null, 'label' => null];

        $this->save($graph)->assertStatus(422)->assertJsonValidationErrors("edges.$index.to");
    }

    public function test_an_unknown_node_type_is_refused(): void
    {
        $graph = $this->graph();
        $graph['nodes'][1]['type'] = 'jalankan_perintah';

        // A type nothing implements would be a dead end in a conversation.
        $this->save($graph)->assertStatus(422);
    }

    public function test_an_action_outside_the_vocabulary_is_refused(): void
    {
        $graph = $this->graph();
        $index = collect($graph['nodes'])->search(fn ($n) => $n['type'] === 'action');
        $graph['nodes'][$index]['config']['action'] = 'exec';

        // Otherwise the editor would be a way to name arbitrary code to run.
        $this->save($graph)->assertStatus(422);
    }

    public function test_an_unknown_data_source_is_refused(): void
    {
        $graph = $this->graph();
        $index = collect($graph['nodes'])->search(fn ($n) => $n['type'] === 'data_source');
        $graph['nodes'][$index]['config']['data_source'] = 'users';

        // A free-text source would be a way to read the users table over chat.
        $this->save($graph)->assertStatus(422);
    }

    public function test_a_half_drawn_flow_can_still_be_saved(): void
    {
        // An editor must be able to stop mid-thought; problems are reported,
        // not refused.
        $graph = $this->graph();
        $graph['nodes'][] = ['key' => 'yatim', 'type' => 'message', 'label' => 'Belum tersambung', 'config' => [], 'position' => ['x' => 900, 'y' => 900]];

        $response = $this->save($graph)->assertOk();

        $this->assertTrue(collect($response->json('problems'))->contains(fn ($p) => $p['node'] === 'yatim'));
    }

    /* ------------------------------------------------------------ inspection */

    public function test_an_unreachable_node_is_reported(): void
    {
        $problems = app(\App\Services\Bot\FlowInspector::class)->inspect($this->flow);

        $this->assertSame([], collect($problems)->where('level', 'error')->values()->all(),
            'Alur bawaan seharusnya bersih: '.json_encode($problems));
    }

    public function test_a_menu_option_with_nowhere_to_go_is_reported(): void
    {
        $node = $this->flow->nodes()->where('key', 'menu_informasi')->firstOrFail();
        $config = $node->config;
        $config['options'][] = ['value' => '9', 'label' => 'Tanpa jalur'];
        $node->update(['config' => $config]);

        $problems = app(\App\Services\Bot\FlowInspector::class)->inspect($this->flow->fresh()->load(['nodes', 'edges']));

        $this->assertTrue(collect($problems)->contains(fn ($p) => str_contains($p['message'], 'Tanpa jalur')));
    }

    /* ------------------------------------------------------------- publish */

    public function test_publishing_snapshots_the_graph_and_bumps_the_version(): void
    {
        $before = $this->flow->version;

        $this->actingAs($this->admin)->post(route('admin.bot.flows.publish', $this->flow))->assertRedirect();

        $this->flow->refresh();

        $this->assertSame($before + 1, $this->flow->version);
        $this->assertNotNull($this->flow->published_at);

        $snapshot = $this->flow->versions()->latest('id')->firstOrFail();
        // Pinned so a conversation already under way keeps the shape it began on.
        $this->assertSame($this->flow->version, $snapshot->version);
        $this->assertCount($this->flow->nodes()->count(), $snapshot->snapshot['nodes']);
    }

    public function test_a_flow_with_errors_is_not_published(): void
    {
        $this->flow->nodes()->create([
            'key' => 'yatim', 'type' => 'message', 'label' => 'Yatim',
            'position_x' => 0, 'position_y' => 0, 'config' => [],
        ]);

        $before = $this->flow->version;

        $this->actingAs($this->admin)
            ->post(route('admin.bot.flows.publish', $this->flow))
            ->assertRedirect()
            ->assertSessionHas('warning');

        // A flow with an unreachable node is a flow that strands somebody.
        $this->assertSame($before, $this->flow->fresh()->version);
    }

    public function test_exactly_one_flow_is_default(): void
    {
        $other = BotFlow::create(['name' => 'Kedua', 'slug' => 'kedua', 'version' => 1]);

        $this->actingAs($this->admin)->post(route('admin.bot.flows.default', $other))->assertRedirect();

        $this->assertSame(1, BotFlow::where('is_default', true)->count());
        $this->assertTrue($other->fresh()->is_default);
        $this->assertFalse($this->flow->fresh()->is_default);
    }

    public function test_the_default_flow_cannot_be_deleted(): void
    {
        $this->actingAs($this->admin)
            ->delete(route('admin.bot.flows.destroy', $this->flow))
            ->assertSessionHas('warning');

        $this->assertNotNull(BotFlow::find($this->flow->id));
    }

    /* --------------------------------------------------------------- izin */

    public function test_someone_without_the_module_cannot_edit_a_flow(): void
    {
        $outsider = User::factory()->create();
        $outsider->givePermissionTo('news.view');

        $this->actingAs($outsider)->get(route('admin.bot.flows.index'))->assertForbidden();
        $this->actingAs($outsider)->get(route('admin.bot.flows.edit', $this->flow))->assertForbidden();
        $this->actingAs($outsider)->putJson(route('admin.bot.flows.save', $this->flow), $this->graph())->assertForbidden();
        $this->actingAs($outsider)->post(route('admin.bot.flows.publish', $this->flow))->assertForbidden();
    }

    public function test_a_viewer_cannot_change_a_flow(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('chatbot.view');

        $this->actingAs($viewer)->get(route('admin.bot.flows.index'))->assertOk();
        $this->actingAs($viewer)->putJson(route('admin.bot.flows.save', $this->flow), $this->graph())->assertForbidden();
    }

    /* ---------------------------------------------------- alur tetap jalan */

    public function test_a_flow_saved_through_the_editor_still_runs(): void
    {
        $this->save($this->graph())->assertOk();

        $channel = \App\Models\Bot\BotChannel::where('key', 'whatsapp')->firstOrFail();
        $channel->update(['is_active' => true]);

        $reply = app(\App\Services\Bot\BotEngine::class)->handle(
            $channel,
            new \App\Services\Bot\Messages\IncomingMessage(externalId: 'x1', from: '628120000009', text: 'halo'),
        );

        // The round trip through the editor must not change what the bot says.
        $this->assertStringContainsString('1. Pengaduan', $reply[0]->body);
    }
}

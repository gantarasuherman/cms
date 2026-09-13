<?php

namespace Tests\Feature\Bot;

use App\Models\Bot\BotChannel;
use App\Models\Bot\BotContact;
use App\Models\Bot\BotConversation;
use App\Models\Bot\BotDataSource;
use App\Models\Bot\BotFlow;
use App\Models\Bot\BotRecipient;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Support\BotNodes;
use Database\Seeders\BotFlowSeeder;
use Database\Seeders\ComplaintCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BotSchemaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ComplaintCategorySeeder::class);
        $this->seed(BotFlowSeeder::class);
    }

    /* --------------------------------------------------------------- flow */

    public function test_the_default_flow_is_seeded_whole(): void
    {
        $flow = BotFlow::where('slug', 'alur-utama')->firstOrFail();

        $this->assertTrue($flow->is_default);
        // Counted against the seeder rather than a literal: the default flow is
        // meant to gain steps, and a test that pins its size only ever reports
        // that it grew.
        $this->assertGreaterThanOrEqual(13, $flow->nodes->count());
        $this->assertGreaterThanOrEqual(21, $flow->edges->count());
        $this->assertSame('start', $flow->startNode()?->key);
    }

    public function test_the_flow_has_exactly_one_entry_point(): void
    {
        // Two starts would make which one runs a matter of insert order.
        $this->assertCount(1, BotFlow::firstOrFail()->nodes->where('type', BotNodes::START));
    }

    public function test_every_edge_joins_two_nodes_that_exist(): void
    {
        $flow = BotFlow::firstOrFail();
        $keys = $flow->nodes->pluck('key')->all();

        foreach ($flow->edges as $edge) {
            $this->assertContains($edge->from_node, $keys, "Sambungan dari {$edge->from_node} menggantung.");
            $this->assertContains($edge->to_node, $keys, "Sambungan ke {$edge->to_node} menggantung.");
        }
    }

    public function test_every_node_is_reachable_from_the_start(): void
    {
        $flow = BotFlow::firstOrFail();
        $edges = $flow->edges->groupBy('from_node');

        $seen = [];
        $queue = ['start'];

        while ($queue) {
            $key = array_shift($queue);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;

            foreach ($edges->get($key, collect()) as $edge) {
                $queue[] = $edge->to_node;
            }
        }

        // An unreachable node is one an administrator drew and nobody will see.
        $orphans = $flow->nodes->pluck('key')->reject(fn ($key) => isset($seen[$key]))->values();
        $this->assertCount(0, $orphans, 'Node tak terjangkau: '.$orphans->implode(', '));
    }

    public function test_every_node_type_is_one_the_engine_implements(): void
    {
        foreach (BotFlow::firstOrFail()->nodes as $node) {
            $this->assertTrue(BotNodes::isType($node->type), "Tipe node tidak dikenal: {$node->type}");
        }
    }

    public function test_a_menu_node_has_a_route_for_every_option_it_offers(): void
    {
        $flow = BotFlow::firstOrFail();
        $edges = $flow->edges->groupBy('from_node');

        foreach ($flow->nodes->where('type', BotNodes::MENU) as $node) {
            // A menu whose options come from a table is routed by one edge.
            if ($node->setting('options_from')) {
                continue;
            }

            foreach ($node->options() as $option) {
                $this->assertTrue(
                    $edges->get($node->key, collect())->contains('condition', $option['value']),
                    "Pilihan {$option['value']} pada {$node->key} tidak mengarah ke mana pun.",
                );
            }
        }
    }

    public function test_the_graph_export_is_one_shape_for_every_reader(): void
    {
        $graph = BotFlow::firstOrFail()->toGraph();

        $this->assertSame(['flow', 'nodes', 'edges'], array_keys($graph));
        $this->assertSame(['key', 'type', 'label', 'config', 'position'], array_keys($graph['nodes'][0]));
        $this->assertSame(['from', 'to', 'condition', 'label'], array_keys($graph['edges'][0]));
    }

    /* ---------------------------------------------------------- categories */

    public function test_evidence_rules_live_on_the_category_not_in_code(): void
    {
        $this->assertSame(['image', 'location'], ComplaintCategory::where('slug', 'jalan')->firstOrFail()->evidenceRequired());
        $this->assertSame([], ComplaintCategory::where('slug', 'lainnya')->firstOrFail()->evidenceRequired());
    }

    /* ------------------------------------------------------------- tickets */

    public function test_a_ticket_is_unguessable_rather_than_sequential(): void
    {
        $tickets = collect(range(1, 60))->map(fn () => Complaint::newTicket());

        $this->assertCount(60, $tickets->unique(), 'Tiket harus unik.');

        foreach ($tickets as $ticket) {
            $this->assertMatchesRegularExpression('/^ADU-[A-Z0-9]{8}$/', $ticket);
            // Knowing one ticket must not reveal the next.
            $this->assertDoesNotMatchRegularExpression('/^ADU-0*\d+$/', $ticket);
            // Characters people misread when copying a code by hand.
            $this->assertDoesNotMatchRegularExpression('/[O0I1]/', substr($ticket, 4));
        }
    }

    /* -------------------------------------------------------------- routing */

    public function test_a_recipient_only_hears_about_the_categories_it_covers(): void
    {
        $jalan = ComplaintCategory::where('slug', 'jalan')->firstOrFail();
        $irigasi = ComplaintCategory::where('slug', 'irigasi')->firstOrFail();

        $roads = BotRecipient::create([
            'name' => 'Admin Bidang Jalan', 'channel' => 'whatsapp',
            'destination' => '628120000001', 'is_active' => true, 'can_command' => true,
        ]);
        $roads->categories()->attach($jalan);

        $group = BotRecipient::create([
            'name' => 'Grup Irigasi', 'channel' => 'telegram',
            'destination' => '-1001234567890', 'is_active' => true, 'can_command' => true,
        ]);
        $group->categories()->attach($irigasi);

        $this->assertSame(['Admin Bidang Jalan'], BotRecipient::forCategory($jalan->id)->pluck('name')->all());
        $this->assertSame(['Grup Irigasi'], BotRecipient::forCategory($irigasi->id)->pluck('name')->all());
    }

    public function test_a_destination_cannot_be_registered_twice_on_one_channel(): void
    {
        BotRecipient::create(['name' => 'A', 'channel' => 'telegram', 'destination' => '-100', 'is_active' => true]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        BotRecipient::create(['name' => 'B', 'channel' => 'telegram', 'destination' => '-100', 'is_active' => true]);
    }

    /* ------------------------------------------------------------- channels */

    public function test_channels_are_seeded_switched_off(): void
    {
        // A channel that answers the public the moment it is seeded is one
        // nobody has reviewed.
        $this->assertSame(2, BotChannel::count());
        $this->assertSame(0, BotChannel::where('is_active', true)->count());
    }

    public function test_a_channel_without_credentials_is_not_ready_even_when_active(): void
    {
        config(['bot.whatsapp.token' => null, 'bot.whatsapp.phone_number_id' => null]);

        $channel = BotChannel::where('key', 'whatsapp')->firstOrFail();
        $channel->update(['is_active' => true]);

        $this->assertTrue($channel->is_active);
        $this->assertFalse($channel->credentialsPresent());
        $this->assertFalse($channel->isReady(), 'Aktif tanpa kredensial berarti bisu, bukan siap.');
    }

    /* ------------------------------------------------------------- sessions */

    public function test_a_conversation_remembers_answers_by_node(): void
    {
        $channel = BotChannel::firstOrFail();
        $contact = BotContact::create(['bot_channel_id' => $channel->id, 'external_id' => '628120000009', 'phone' => '628120000009']);

        $conversation = BotConversation::create([
            'bot_channel_id' => $channel->id,
            'bot_contact_id' => $contact->id,
            'bot_flow_id' => BotFlow::firstOrFail()->id,
            'flow_version' => 1,
            'current_node' => 'isi_aduan',
            'status' => 'active',
        ]);

        $conversation->remember('description', 'Jalan berlubang di depan balai desa.');
        $conversation->save();

        $this->assertSame('Jalan berlubang di depan balai desa.', $conversation->fresh()->answer('description'));
        $this->assertNull($conversation->fresh()->answer('ticket'));
    }

    public function test_an_expired_conversation_is_not_open(): void
    {
        $channel = BotChannel::firstOrFail();
        $contact = BotContact::create(['bot_channel_id' => $channel->id, 'external_id' => '62812000000A']);

        BotConversation::create([
            'bot_channel_id' => $channel->id, 'bot_contact_id' => $contact->id,
            'status' => 'active', 'expires_at' => now()->subMinute(),
        ]);

        $this->assertSame(0, BotConversation::open()->count());
    }

    public function test_a_phone_number_is_masked_for_the_operator_list(): void
    {
        $contact = BotContact::create([
            'bot_channel_id' => BotChannel::firstOrFail()->id,
            'external_id' => '628123456789', 'phone' => '628123456789',
        ]);

        $masked = $contact->maskedPhone();

        $this->assertStringStartsWith('6281', $masked);
        $this->assertStringEndsWith('789', $masked);
        $this->assertStringNotContainsString('23456', $masked);
    }

    /* --------------------------------------------------------- data sources */

    public function test_data_sources_are_limited_to_published_site_content(): void
    {
        foreach (BotDataSource::all() as $source) {
            // A free-text model name here would be a way to read the users
            // table over WhatsApp.
            $this->assertArrayHasKey($source->source, BotNodes::dataSources(), $source->slug);
        }
    }

    public function test_reseeding_replaces_the_graph_rather_than_duplicating_it(): void
    {
        $this->seed(BotFlowSeeder::class);

        $flow = BotFlow::where('slug', 'alur-utama')->firstOrFail();

        $this->assertSame(1, BotFlow::where('slug', 'alur-utama')->count());
        $this->assertSame($flow->nodes->pluck('key')->unique()->count(), $flow->nodes->count());
        $this->assertGreaterThanOrEqual(13, $flow->nodes->count());
    }
}

<?php

namespace Tests\Feature\Bot;

use App\Models\Bot\BotChannel;
use App\Models\Bot\BotContact;
use App\Models\Bot\BotConversation;
use App\Models\Bot\BotMessage;
use App\Models\BotQuestionTopic;
use App\Models\Faq;
use App\Models\User;
use App\Services\Bot\QuestionDigest;
use Database\Seeders\BotFlowSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestionDigestTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private BotConversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(BotFlowSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super Admin');

        $this->conversation = $this->conversationFor('6281200000001');
    }

    private function conversationFor(string $phone): BotConversation
    {
        $channel = BotChannel::firstOrFail();

        $contact = BotContact::create([
            'bot_channel_id' => $channel->getKey(),
            'external_id' => $phone,
            'name' => 'Warga',
            'phone' => $phone,
        ]);

        return BotConversation::create([
            'bot_channel_id' => $channel->getKey(),
            'bot_contact_id' => $contact->getKey(),
            'status' => 'active',
            'state' => [],
        ]);
    }

    private function ask(string $body, string $node = 'minta_pertanyaan', ?BotConversation $in = null): void
    {
        BotMessage::create([
            'bot_conversation_id' => ($in ?? $this->conversation)->getKey(),
            'direction' => 'in',
            'type' => 'text',
            'body' => $body,
            'node_key' => $node,
        ]);
    }

    private function digest(): QuestionDigest
    {
        return app(QuestionDigest::class);
    }

    public function test_the_same_question_asked_differently_is_one_topic(): void
    {
        $this->ask('Berapa lama proses izin mendirikan bangunan?');
        $this->ask('berapa lama proses izin mendirikan bangunan');
        $this->ask('Izin mendirikan bangunan berapa lama ya kak?');

        $topics = $this->digest()->topics();

        $this->assertCount(1, $topics, 'Tiga penulisan, satu pertanyaan.');
        $this->assertSame(3, $topics[0]['asks']);
        $this->assertCount(3, $topics[0]['variants']);
    }

    public function test_different_questions_stay_apart(): void
    {
        $this->ask('Berapa lama proses izin mendirikan bangunan?');
        $this->ask('Di mana kantor dinas pekerjaan umum berada?');

        $this->assertCount(2, $this->digest()->topics());
    }

    public function test_a_complaint_is_not_a_question(): void
    {
        // `isi_aduan` stores a description, not a question. Mining it would
        // put somebody's report of a pothole into the public FAQ.
        $this->ask('Jalan di depan rumah saya berlubang parah', 'isi_aduan');
        $this->ask('Berapa lama proses izin mendirikan bangunan?');

        $topics = $this->digest()->topics();

        $this->assertCount(1, $topics);
        $this->assertStringContainsString('izin', $topics[0]['question']);
    }

    public function test_how_many_people_asked_is_counted_apart_from_how_often(): void
    {
        $this->ask('Berapa lama proses izin mendirikan bangunan?');
        $this->ask('Berapa lama proses izin mendirikan bangunan?');
        $this->ask('Berapa lama proses izin mendirikan bangunan?', 'minta_pertanyaan', $this->conversationFor('6281200000002'));

        $topic = $this->digest()->topics()[0];

        // One person asking three times is not the same signal as three
        // people asking once, and the screen shows both.
        $this->assertSame(3, $topic['asks']);
        $this->assertSame(2, $topic['askers']);
    }

    public function test_the_screen_lists_what_people_keep_asking(): void
    {
        $this->ask('Berapa lama proses izin mendirikan bangunan?');

        $this->actingAs($this->admin)
            ->get(route('admin.bot.questions.index'))
            ->assertOk()
            ->assertSee('Berapa lama proses izin mendirikan bangunan?');
    }

    public function test_promoting_opens_an_unpublished_faq_for_an_answer(): void
    {
        $this->ask('Berapa lama proses izin mendirikan bangunan?');

        $this->actingAs($this->admin)
            ->post(route('admin.bot.questions.promote'), ['question' => 'Berapa lama proses izin mendirikan bangunan?'])
            ->assertRedirect();

        $faq = Faq::firstOrFail();

        $this->assertSame('Berapa lama proses izin mendirikan bangunan?', $faq->question);
        // Empty and unpublished: only a person can write the answer, and a
        // blank FAQ card on the public site would be worse than none.
        $this->assertSame('', $faq->answer);
        $this->assertFalse($faq->is_active);

        $topic = BotQuestionTopic::firstOrFail();
        $this->assertSame(BotQuestionTopic::PROMOTED, $topic->status);
        $this->assertSame($faq->getKey(), $topic->faq_id);
    }

    public function test_a_promoted_question_leaves_the_list_but_keeps_its_count(): void
    {
        $this->ask('Berapa lama proses izin mendirikan bangunan?');
        $this->ask('Berapa lama proses izin mendirikan bangunan?');

        $this->actingAs($this->admin)->post(route('admin.bot.questions.promote'), [
            'question' => 'Berapa lama proses izin mendirikan bangunan?',
        ]);

        $this->assertCount(0, $this->digest()->topics());

        $promoted = $this->digest()->topics(BotQuestionTopic::PROMOTED);
        $this->assertCount(1, $promoted);
        $this->assertSame(2, $promoted[0]['asks']);
    }

    public function test_promoting_the_same_question_twice_opens_the_first_faq(): void
    {
        $this->ask('Berapa lama proses izin mendirikan bangunan?');

        $this->actingAs($this->admin)->post(route('admin.bot.questions.promote'), ['question' => 'Berapa lama proses izin mendirikan bangunan?']);
        $this->actingAs($this->admin)->post(route('admin.bot.questions.promote'), ['question' => 'berapa lama proses izin mendirikan bangunan']);

        // Two FAQs saying the same thing is how an FAQ page stops being read.
        $this->assertSame(1, Faq::count());
    }

    public function test_a_set_aside_question_can_be_brought_back(): void
    {
        $this->ask('Berapa lama proses izin mendirikan bangunan?');

        $this->actingAs($this->admin)->post(route('admin.bot.questions.ignore'), ['question' => 'Berapa lama proses izin mendirikan bangunan?']);
        $this->assertCount(0, $this->digest()->topics());

        $this->actingAs($this->admin)->delete(route('admin.bot.questions.restore', BotQuestionTopic::firstOrFail()));
        $this->assertCount(1, $this->digest()->topics());
    }

    public function test_somebody_without_faq_rights_cannot_promote(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('Viewer');

        $this->ask('Berapa lama proses izin mendirikan bangunan?');

        $this->actingAs($viewer)
            ->post(route('admin.bot.questions.promote'), ['question' => 'Berapa lama proses izin mendirikan bangunan?'])
            ->assertForbidden();

        $this->assertSame(0, Faq::count());
    }
}

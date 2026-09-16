<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bot\BotChannel;
use App\Models\Bot\BotContact;
use App\Models\Bot\BotConversation;
use App\Models\Bot\BotMessage;
use App\Models\Bot\BotRecipient;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Services\Ai\AiSettings;
use App\Services\Bot\BotEngine;
use App\Services\Bot\Messages\IncomingMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Mencoba percakapan dari dalam panel, tanpa ponsel.
 *
 * Memakai BotEngine yang sama persis dengan yang melayani WhatsApp dan
 * Telegram sungguhan — bukan tiruan. Pratinjau yang dibangun dari jalur kode
 * berbeda adalah pratinjau yang bisa berbohong: ia akan tetap menjawab rapi
 * pada hari mesin aslinya rusak.
 *
 * Karena mesinnya sungguhan, percakapannya juga sungguhan: state tersimpan,
 * sehingga alur bermenu ("balas 1") dapat ditelusuri sampai habis. Konsekuensi
 * jujurnya adalah pengaduan yang diajukan dari sini benar-benar tercatat, dan
 * petugas benar-benar dikabari. Karena itu ada tombol Reset, dan percakapan
 * ini memakai kontak tersendiri per administrator sehingga tidak pernah
 * tercampur dengan percakapan warga.
 */
class BotSimulatorController extends Controller
{
    public function __construct(private readonly AiSettings $ai)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', BotChannel::class);

        $channel = $this->channel($request);

        return view('admin.bot.simulator.index', [
            'channels' => BotChannel::orderBy('name')->get(),
            'channel' => $channel,
            'messages' => $channel ? $this->transcript($channel, $request) : collect(),
            'checks' => $channel ? $this->checks($channel) : [],
        ]);
    }

    public function send(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', BotChannel::class);

        $data = $request->validate([
            'channel' => ['required', 'string', 'exists:bot_channels,key'],
            'text' => ['required', 'string', 'max:1000'],
        ]);

        $channel = BotChannel::where('key', $data['channel'])->firstOrFail();

        app(BotEngine::class)->handle($channel, new IncomingMessage(
            // Penanda unik per pesan: mesin menolak pengiriman ulang dengan id
            // yang sama, persis seperti saat platform mengirim ulang update.
            externalId: 'sim-'.$request->user()->getKey().'-'.now()->getTimestampMs(),
            from: $this->from($request),
            text: $data['text'],
            senderName: $request->user()->name.' (uji coba)',
        ));

        return redirect()
            ->route('admin.bot.simulator.index', ['channel' => $channel->key])
            ->withFragment('percakapan');
    }

    /**
     * Membuang percakapan uji beserta jejaknya.
     *
     * Termasuk pengaduan yang sempat diajukan dari sini: membiarkannya berarti
     * angka pada layar pengaduan ikut menghitung latihan, dan tidak ada yang
     * lebih merepotkan daripada laporan yang tercampur data percobaan.
     */
    public function reset(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', BotChannel::class);

        $contacts = BotContact::where('external_id', $this->from($request))->get();

        DB::transaction(function () use ($contacts) {
            foreach ($contacts as $contact) {
                Complaint::where('bot_contact_id', $contact->getKey())->forceDelete();

                BotMessage::whereIn(
                    'bot_conversation_id',
                    BotConversation::where('bot_contact_id', $contact->getKey())->select('id'),
                )->delete();

                BotConversation::where('bot_contact_id', $contact->getKey())->delete();
                $contact->delete();
            }
        });

        return back()->with('success', 'Percakapan uji dan pengaduan yang dibuat darinya telah dihapus.');
    }

    /** Identitas pengirim uji: satu per administrator, tidak pernah milik warga. */
    private function from(Request $request): string
    {
        return 'simulator-'.$request->user()->getKey();
    }

    private function channel(Request $request): ?BotChannel
    {
        $key = $request->query('channel');

        return ($key ? BotChannel::where('key', $key)->first() : null)
            ?? BotChannel::orderByDesc('is_active')->orderBy('name')->first();
    }

    private function transcript(BotChannel $channel, Request $request): \Illuminate\Support\Collection
    {
        $conversation = BotConversation::query()
            ->where('bot_channel_id', $channel->getKey())
            ->whereHas('contact', fn ($q) => $q->where('external_id', $this->from($request)))
            ->latest('id')
            ->first();

        if (! $conversation) {
            return collect();
        }

        return BotMessage::where('bot_conversation_id', $conversation->getKey())
            ->orderBy('id')
            ->get();
    }

    /**
     * Apa yang sudah siap dan apa yang belum, dinyatakan sebagai kalimat.
     *
     * Tiap baris menyebutkan akibatnya bila belum siap, bukan sekadar "tidak
     * aktif": yang perlu diketahui administrator adalah apa yang tidak akan
     * terjadi, bukan nama setelan yang kosong.
     *
     * @return array<int, array{label: string, ok: bool, note: string}>
     */
    private function checks(BotChannel $channel): array
    {
        $missing = $channel->missingCredentials();
        $uncovered = ComplaintCategory::where('is_active', true)
            ->whereDoesntHave('recipients', fn ($q) => $q->where('is_active', true))
            ->count();
        $pics = BotRecipient::where('is_active', true)->count();

        return [
            [
                'label' => 'Kredensial '.$channel->name,
                'ok' => $missing === [],
                'note' => $missing === []
                    ? ($channel->verified_at
                        ? 'Lengkap dan sudah diuji '.$channel->verified_at->diffForHumans().'.'
                        : 'Lengkap, tetapi belum pernah diuji. Tekan Uji pada Pengaturan Chatbot.')
                    : 'Belum ada: '.implode(', ', $missing).'. Pesan sungguhan tidak akan terkirim.',
            ],
            [
                'label' => 'Saluran aktif',
                'ok' => (bool) $channel->is_active,
                'note' => $channel->is_active
                    ? 'Pesan dari warga akan dilayani.'
                    : 'Nonaktif — warga tidak dilayani. Simulator di bawah tetap dapat dipakai.',
            ],
            [
                'label' => 'Alur percakapan',
                'ok' => (bool) $channel->bot_flow_id,
                'note' => $channel->bot_flow_id
                    ? 'Memakai alur "'.($channel->flow?->name ?? '—').'".'
                    : 'Belum disambungkan ke alur mana pun; bot tidak tahu harus menjawab apa.',
            ],
            [
                'label' => 'Petugas penerima',
                'ok' => $pics > 0 && $uncovered === 0,
                'note' => $pics === 0
                    ? 'Belum ada satu pun. Pengaduan tersimpan, tetapi tidak ada yang dikabari.'
                    : ($uncovered === 0
                        ? $pics.' petugas, seluruh kategori terpegang.'
                        : $pics.' petugas, tetapi '.$uncovered.' kategori belum ada yang memegang.'),
            ],
            [
                'label' => 'Bantuan AI',
                'ok' => $this->ai->enabled() && (! $this->ai->needsKey() || (bool) $this->ai->apiKey()),
                'note' => ! $this->ai->enabled()
                    ? 'Mati. Jawaban disusun dari pencarian FAQ, bukan kalimat yang dirangkai. Ini bukan kesalahan.'
                    : ((! $this->ai->needsKey() || $this->ai->apiKey())
                        ? 'Aktif memakai '.$this->ai->provider().' ('.$this->ai->model().').'
                        : 'Dinyalakan tetapi kuncinya kosong — jawaban tetap jatuh ke pencarian FAQ.'),
            ],
        ];
    }
}

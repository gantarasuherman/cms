<?php

namespace App\Http\Controllers\Api\Bot;

use App\Http\Controllers\Controller;
use App\Models\Bot\BotChannel;
use App\Models\Bot\BotContact;
use App\Models\Bot\BotConversation;
use App\Models\Bot\BotRecipient;
use App\Services\Bot\BotEngine;
use App\Services\Bot\CommandHandler;
use App\Services\Bot\Messages\IncomingMessage;
use App\Services\Bot\Messages\OutgoingMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * The one way a message gets into the system.
 *
 * The Python service owns the platforms: it receives the webhook, verifies the
 * signature against the raw body (which only it has), downloads any media, and
 * posts the result here in one normalised shape. Everything after that —
 * sessions, the flow, complaints, authority — is decided in Laravel, so there
 * is one place where the rules live rather than two that can drift.
 */
class InboundController extends Controller
{
    public function __construct(
        private readonly BotEngine $engine,
        private readonly CommandHandler $commands,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'channel' => ['required', 'string', Rule::in(array_keys(BotChannel::KEYS))],
            // The platform's own message id. Both Meta and Telegram retry a
            // delivery they believe failed, so this is what stops one report
            // being filed twice.
            'external_id' => ['required', 'string', 'max:190'],
            'from' => ['required', 'string', 'max:128'],
            // Any short kind is accepted, not a fixed list. A sticker, a
            // voice note or a shared contact is a thing a person did in a
            // conversation — answering 422 would make the platform retry it
            // forever instead of letting the flow say "that is not what I
            // asked for".
            'type' => ['nullable', 'string', 'max:32', 'regex:/^[a-z_]+$/'],
            'text' => ['nullable', 'string', 'max:4096'],
            // A path the bot service already wrote to the shared private disk.
            'media_path' => ['nullable', 'string', 'max:255'],
            'media_mime' => ['nullable', 'string', 'max:128'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'sender_name' => ['nullable', 'string', 'max:120'],
            'sender_username' => ['nullable', 'string', 'max:64'],
            'sender_avatar_path' => ['nullable', 'string', 'max:255'],
            'sender_avatar_ref' => ['nullable', 'string', 'max:190'],
            'raw' => ['nullable', 'array'],
        ]);

        $channel = BotChannel::where('key', $data['channel'])->first();

        if (! $channel || ! $channel->is_active) {
            return response()->json(['status' => 'ignored', 'reason' => 'Kanal tidak aktif.', 'messages' => []]);
        }

        if (! $this->firstSighting($data['channel'], $data['external_id'])) {
            // A retry of something already handled. Answering 200 with nothing
            // is what stops the platform retrying again.
            return response()->json(['status' => 'duplicate', 'messages' => []]);
        }

        $message = new IncomingMessage(
            externalId: $data['external_id'],
            from: $data['from'],
            type: $data['type'] ?? 'text',
            text: $data['text'] ?? null,
            mediaPath: $data['media_path'] ?? null,
            mediaMime: $data['media_mime'] ?? null,
            latitude: isset($data['latitude']) ? (float) $data['latitude'] : null,
            longitude: isset($data['longitude']) ? (float) $data['longitude'] : null,
            senderName: $data['sender_name'] ?? null,
            senderUsername: $data['sender_username'] ?? null,
            senderAvatarPath: $data['sender_avatar_path'] ?? null,
            senderAvatarRef: $data['sender_avatar_ref'] ?? null,
            raw: $data['raw'] ?? [],
        );

        // A message from a registered officer destination is an instruction,
        // not a member of the public working through the menu.
        $recipient = BotRecipient::active()
            ->where('channel', $channel->key)
            ->where('destination', $message->from)
            ->first();

        if ($recipient) {
            // Triase yang sedang berjalan menangkap jawaban bernomor lebih
            // dulu — tetapi hanya angka yang memang ditawarkannya. Petugas
            // tetap boleh menyela dengan /info atau /jawab tanpa kehilangan
            // tempatnya dalam triase.
            $answered = $this->commands->continueTriage($recipient, $message);

            if ($answered !== null) {
                return $this->reply('command', $answered);
            }

            if ($message->isCommand()) {
                $replies = $this->commands->handle($channel, $message, $recipient);

                if ($replies !== null) {
                    return $this->reply('command', $replies);
                }
            }

            // Petugas boleh menjadi warga, tetapi harus memintanya.
            //
            // Nomor terdaftar adalah meja kerja: menjatuhkannya ke menu
            // "1. Pengaduan, 2. Cek Aduan" pada setiap pesan membuat grup
            // petugas mulai mengisi formulir pengaduan tiap kali ada yang
            // mengetik, dan balasan bot bercampur dengan kabar pengaduan yang
            // sedang dibaca.
            //
            // Tetapi petugas juga orang: ia punya jalan yang berlubang di
            // depan rumahnya. `/menu` membuka alur warga untuknya, dan selama
            // percakapan itu masih hidup setiap pesan biasa diteruskan ke sana
            // — kalau tidak, ia akan tersangkut pada pertanyaan pertama.
            if ($this->citizenMode($channel, $message)) {
                return $this->reply('handled', $this->engine->handle($channel, $message));
            }

            // Diam kecuali memang diajak bicara.
            //
            // Nomor petugas sering berupa GRUP, tempat orang berbicara satu
            // sama lain sepanjang hari. Membalas setiap pesan biasa dengan
            // daftar perintah membuat bot menyela tiap kali ada yang mengetik
            // — daftar yang sama, berulang-ulang, sampai kabar pengaduan yang
            // sebenarnya tenggelam di antaranya. Grup yang berisik adalah grup
            // yang berhenti dibaca.
            //
            // Pesan berawalan garis miring tetap dijawab meski perintahnya
            // tidak dikenali: itu salah ketik, dan salah ketik yang dibalas
            // diam membuat orang mengira botnya mati.
            if (! $message->isCommand()) {
                return $this->reply('ignored', []);
            }

            return $this->reply('command', [
                OutgoingMessage::text($this->commands->help($recipient)),
            ]);
        }

        return $this->reply('handled', $this->engine->handle($channel, $message));
    }

    /**
     * Apakah pesan ini harus dilayani sebagai warga, bukan sebagai petugas.
     *
     * Dua keadaan, dan keduanya perlu:
     *
     * - `/menu` (atau `/mulai`) — permintaan tegas untuk membuka alur warga.
     * - Percakapan warga yang masih hidup — tanpa ini, pesan berikutnya
     *   dijawab daftar perintah dan orangnya tersangkut selamanya pada
     *   pertanyaan pertama.
     *
     * Sengaja hanya menerima bentuk bergaris miring. Kata "menu" telanjang
     * lazim terucap dalam obrolan grup petugas, dan bot yang ikut menyela
     * setiap kali kata itu lewat akan membuat orang berhenti membacanya.
     */
    private function citizenMode(BotChannel $channel, IncomingMessage $message): bool
    {
        if (in_array($message->command(), ['menu', 'mulai'], true)) {
            return true;
        }

        $contact = BotContact::where('bot_channel_id', $channel->getKey())
            ->where('external_id', $message->from)
            ->first();

        return $contact !== null
            && BotConversation::open()->where('bot_contact_id', $contact->getKey())->exists();
    }

    /**
     * True the first time this platform message is seen.
     *
     * The uniqueness is enforced by the database rather than by reading first
     * and writing after: two retries arriving together would both pass a read
     * check and both be handled.
     */
    private function firstSighting(string $channel, string $externalId): bool
    {
        try {
            DB::table('bot_webhook_events')->insert([
                'channel' => $channel,
                'external_id' => $externalId,
                'received_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            return false;
        }

        return true;
    }

    /** @param array<int, OutgoingMessage> $messages */
    private function reply(string $status, array $messages): JsonResponse
    {
        return response()->json([
            'status' => $status,
            'messages' => array_map(fn (OutgoingMessage $message) => [
                'type' => $message->type,
                'body' => $message->body,
                'media_path' => $message->mediaPath,
            ], $messages),
        ]);
    }
}

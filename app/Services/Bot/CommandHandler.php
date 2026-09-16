<?php

namespace App\Services\Bot;

use App\Models\Bot\BotChannel;
use App\Models\Bot\BotContact;
use App\Models\Bot\BotConversation;
use App\Models\Bot\BotRecipient;
use App\Models\Complaint;
use App\Models\ComplaintAttachment;
use App\Services\Bot\Actions\ComplaintTriage;
use App\Services\Bot\Actions\ReporterReplier;
use App\Services\Bot\Messages\IncomingMessage;
use App\Services\Bot\Messages\OutgoingMessage;
use Illuminate\Support\Facades\DB;

/**
 * Handles officers replying from their own phone or from a Telegram group.
 *
 * Authority comes from the `bot_recipients` row the message arrived from, not
 * from anything in the message itself: a destination that was registered and
 * ticked `can_command` may change a status, and nothing else may. A group
 * where a complaint was merely announced cannot resolve it.
 *
 * Officers are also only allowed to act on complaints in the categories they
 * cover — otherwise being told about a road defect would let someone close an
 * irrigation report they were never shown.
 */
class CommandHandler
{
    private const COMMANDS = [
        'proses' => 'diproses',
        'selesai' => 'selesai',
        'tolak' => 'ditolak',
    ];

    /** Membaca saja: tidak mengubah apa pun, jadi tidak perlu can_command. */
    private const READ_ONLY = ['info', 'bantuan', 'start', 'help'];

    /** Menutup alur warga yang sedang dibuka petugas, kembali ke meja kerja. */
    private const LEAVE = 'petugas';

    /** Menjawab pelapor langsung dari chat, tanpa membuka panel. */
    private const ANSWER = 'jawab';

    /**
     * Menyatakan pengaduan bukan kewenangan dinas ini, lalu mengarahkannya.
     *
     * `triase` tetap diterima: nama lamanya sempat tercetak pada kabar
     * pengaduan, dan perintah yang pernah diajarkan lalu diam-diam dihapus
     * hanya menyisakan orang yang mengira botnya rusak.
     */
    private const TRIAGE = ['bukan', 'triase'];

    /** Menghentikan triase yang sedang berjalan. */
    private const ABORT = 'batal';

    public function __construct(
        private readonly ReporterReplier $replier,
        private readonly ComplaintTriage $triage,
    ) {
    }

    /**
     * Jawaban bernomor atas triase yang sedang berjalan, bila ada.
     *
     * Dipanggil sebelum perintah diperiksa, tetapi hanya menangkap pesan yang
     * memang berupa angka pilihan: petugas tetap boleh menyela dengan /info
     * atau /jawab di tengah triase tanpa kehilangan tempatnya.
     */
    public function continueTriage(BotRecipient $recipient, IncomingMessage $message): ?array
    {
        $pending = $this->triage->pending($recipient);

        if (! $pending) {
            return null;
        }

        if ($message->command() === self::ABORT) {
            $this->triage->cancel($recipient);

            return [OutgoingMessage::text('Triase dihentikan. Pengaduannya tidak diubah.')];
        }

        $answer = $this->triage->answer($recipient, $pending, $message->body());

        return $answer === null ? null : [OutgoingMessage::text($answer)];
    }

    /**
     * @return array<int, OutgoingMessage>|null null when this is not a command
     *                                          message from an authorised place
     */
    public function handle(BotChannel $channel, IncomingMessage $message, BotRecipient $recipient): ?array
    {
        $command = $message->command();

        if ($command === null) {
            return null;
        }

        if ($command === self::LEAVE) {
            $closed = $this->closeCitizenConversation($recipient);

            return [OutgoingMessage::text(
                ($closed ? "Percakapan warga ditutup.\n\n" : '').$this->help($recipient),
            )];
        }

        if ($command === self::ANSWER) {
            return [OutgoingMessage::text($this->answer($message, $recipient))];
        }

        if (in_array($command, self::TRIAGE, true)) {
            return [OutgoingMessage::text($this->openTriage($message, $recipient))];
        }

        if ($command === self::ABORT) {
            $this->triage->cancel($recipient);

            return [OutgoingMessage::text('Tidak ada triase yang sedang berjalan.')];
        }

        if (in_array($command, self::READ_ONLY, true)) {
            return $command === 'info' && $this->ticket($message) !== null
                ? [OutgoingMessage::text($this->info($recipient, (string) $this->ticket($message)))]
                : [OutgoingMessage::text($this->help($recipient))];
        }

        if (! isset(self::COMMANDS[$command])) {
            return null;
        }

        if (! $recipient->can_command) {
            return [OutgoingMessage::text('Nomor atau grup ini tidak berwenang mengubah status pengaduan.')];
        }

        $ticket = $this->ticket($message);

        if ($ticket === null) {
            return [OutgoingMessage::text('Sertakan nomor tiketnya. Contoh: /'.$command.' ADU-K7M2PQR9')];
        }

        $complaint = Complaint::where('ticket', $ticket)->first();

        if (! $complaint) {
            return [OutgoingMessage::text('Tiket '.$ticket.' tidak ditemukan.')];
        }

        if (! $this->covers($recipient, $complaint)) {
            return [OutgoingMessage::text('Pengaduan '.$ticket.' bukan kategori yang ditugaskan ke Anda.')];
        }

        $status = self::COMMANDS[$command];

        if ($complaint->status === $status) {
            return [OutgoingMessage::text('Pengaduan '.$ticket.' memang sudah berstatus '.$complaint->statusLabel().'.')];
        }

        return [OutgoingMessage::text($this->apply($complaint, $status, $message, $recipient))];
    }

    /**
     * Keadaan satu pengaduan, untuk dibaca petugas di chat.
     *
     * Dibatasi kategori yang ditugaskan, sama seperti perintah yang mengubah
     * status: nomor tiket mudah ditebak-tebak, dan uraian pengaduan memuat
     * nama, nomor telepon, serta foto rumah orang.
     */
    private function info(BotRecipient $recipient, string $ticket): string
    {
        $complaint = Complaint::where('ticket', $ticket)->first();

        if (! $complaint) {
            return 'Tiket '.$ticket.' tidak ditemukan.';
        }

        if (! $this->covers($recipient, $complaint)) {
            return 'Pengaduan '.$ticket.' bukan kategori yang ditugaskan ke Anda.';
        }

        $lines = [
            'Tiket: '.$complaint->ticket,
            'Status: '.$complaint->statusLabel(),
            'Kategori: '.($complaint->category?->name ?? 'Tanpa kategori'),
            'Masuk: '.$complaint->created_at?->translatedFormat('d F Y H:i'),
        ];

        if ($complaint->processed_at) {
            $lines[] = 'Diproses: '.$complaint->processed_at->translatedFormat('d F Y H:i');
        }

        if ($complaint->resolved_at) {
            $lines[] = 'Selesai: '.$complaint->resolved_at->translatedFormat('d F Y H:i');
        }

        $lines[] = '';
        $lines[] = $complaint->description;

        if ($complaint->hasLocation()) {
            $lines[] = '';
            $lines[] = 'Lokasi: https://www.google.com/maps?q='.$complaint->latitude.','.$complaint->longitude;
        }

        if ($last = $complaint->updates()->latest('id')->first()) {
            $lines[] = '';
            $lines[] = 'Terakhir diubah oleh '.($last->source_actor ?: 'petugas')
                .($last->note ? ': '.$last->note : '.');
        }

        return implode("\n", $lines);
    }

    /**
     * Menjawab pelapor, dikirim ke kanal tempat ia mengadu.
     *
     * Ada karena pertanyaan warga menunggu kalimat, bukan perubahan status:
     * menandai sebuah pertanyaan "selesai" tanpa pernah menjawabnya membuat
     * antrean terlihat rapi sementara orangnya tidak pernah mendengar apa pun.
     *
     * Menjawab tidak sekaligus menutup pengaduannya. Petugas yang menjawab
     * sebagian, atau menjanjikan tindak lanjut, tidak boleh dipaksa menutup
     * berkasnya karena sudah terlanjur mengetik — `/selesai` tetap terpisah.
     */
    private function answer(IncomingMessage $message, BotRecipient $recipient): string
    {
        // Berbicara kepada warga atas nama instansi, jadi kewenangannya sama
        // dengan mengubah status — bukan sekadar membaca.
        if (! $recipient->can_command) {
            return 'Nomor atau grup ini tidak berwenang menjawab pelapor.';
        }

        $ticket = $this->ticket($message);

        if ($ticket === null) {
            return 'Sertakan nomor tiketnya. Contoh: /jawab ADU-K7M2PQR9 Berkas Anda sudah lengkap, prosesnya lima hari kerja.';
        }

        $body = $this->note($message);

        if ($body === null) {
            return 'Tulis jawabannya setelah nomor tiket. Contoh: /jawab '.$ticket.' Sudah kami tinjau, perbaikan dijadwalkan pekan depan.';
        }

        $complaint = Complaint::where('ticket', $ticket)->first();

        if (! $complaint) {
            return 'Tiket '.$ticket.' tidak ditemukan.';
        }

        if (! $this->covers($recipient, $complaint)) {
            return 'Pengaduan '.$ticket.' bukan kategori yang ditugaskan ke Anda.';
        }

        $sent = $this->replier->send(
            $complaint,
            "Jawaban untuk pengaduan Anda.\n\nTiket: *".$complaint->ticket."*\n\n".$body,
            null,
            $recipient->user,
            $recipient->channel,
            $recipient->name.' ('.$recipient->destination.')',
        );

        if (! $sent) {
            return 'Pengaduan '.$ticket.' tidak berasal dari WhatsApp atau Telegram, jadi tidak ada tujuan untuk membalas.';
        }

        // Perintah lanjutannya dalam blok kode: sekali ketuk di Telegram sudah
        // tersalin, tinggal ditempel — mengetik ulang nomor tiket delapan
        // karakter dari layar adalah cara mudah salah satu huruf.
        return 'Jawaban dikirim ke pelapor '.$ticket.'.'
            ."\n\nPengaduannya masih berstatus ".$complaint->statusLabel()."."
            ."\nBila sudah tuntas:\n```\n/selesai ".$ticket."\n```";
    }

    /** Membuka triase: kewenangan, lalu tolak atau teruskan. */
    private function openTriage(IncomingMessage $message, BotRecipient $recipient): string
    {
        if (! $recipient->can_command) {
            return 'Nomor atau grup ini tidak berwenang mengarahkan pengaduan.';
        }

        $ticket = $this->ticket($message);

        if ($ticket === null) {
            return 'Sertakan nomor tiketnya. Contoh: /bukan ADU-K7M2PQR9';
        }

        $complaint = Complaint::where('ticket', $ticket)->first();

        if (! $complaint) {
            return 'Tiket '.$ticket.' tidak ditemukan.';
        }

        if (! $this->covers($recipient, $complaint)) {
            return 'Pengaduan '.$ticket.' bukan kategori yang ditugaskan ke Anda.';
        }

        return $this->triage->start($recipient, $complaint);
    }

    /**
     * Menutup alur warga yang sedang dibuka nomor ini.
     *
     * Ditandai selesai, bukan dihapus: percakapan separuh jalan tetap terbaca
     * pada riwayat, dan pengaduan yang telanjur diajukan tidak ikut hilang.
     */
    private function closeCitizenConversation(BotRecipient $recipient): bool
    {
        $contact = BotContact::whereHas(
            'channel',
            fn ($q) => $q->where('key', $recipient->channel),
        )->where('external_id', $recipient->destination)->first();

        if (! $contact) {
            return false;
        }

        return BotConversation::open()
            ->where('bot_contact_id', $contact->getKey())
            ->update(['status' => BotConversation::COMPLETED]) > 0;
    }

    /**
     * Daftar perintah, dan apa yang boleh dilakukan nomor ini.
     *
     * Dikirim juga sebagai jawaban atas pesan biasa: petugas terdaftar tidak
     * diberi menu warga, sehingga tanpa ini sebuah "halo" hanya berbalas diam.
     */
    public function help(BotRecipient $recipient): string
    {
        $lines = [
            'Nomor ini terdaftar sebagai petugas penerima pengaduan.',
            '',
            'Perintah yang tersedia:',
            // Daftar yang tidak menyebut cara memanggilnya kembali adalah
            // daftar yang hanya berguna sekali: begitu pesannya tergulung ke
            // atas, tidak ada petunjuk tersisa di layar.
            '/help — menampilkan daftar ini lagi',
            '/info ADU-XXXXXXXX — melihat keadaan satu pengaduan',
        ];

        if ($recipient->can_command) {
            $lines[] = '/bukan ADU-XXXXXXXX — bukan kewenangan kita, arahkan ke instansi lain';
            $lines[] = '/jawab ADU-XXXXXXXX <jawaban> — mengirim jawaban kepada pelapor';
            $lines[] = '/proses ADU-XXXXXXXX — menandai sedang dikerjakan';
            $lines[] = '/selesai ADU-XXXXXXXX — menandai sudah ditangani';
            $lines[] = '/tolak ADU-XXXXXXXX — menandai ditolak';
            $lines[] = '';
            $lines[] = 'Tulisan setelah nomor tiket disimpan sebagai catatan, dan foto yang disertakan pada /selesai tersimpan sebagai bukti tindak lanjut.';
        } else {
            $lines[] = '';
            $lines[] = 'Nomor ini hanya menerima kabar pengaduan; pengubahan status belum diizinkan untuknya.';
        }

        // Petugas juga warga: ia punya jalan berlubang di depan rumahnya.
        $lines[] = '';
        $lines[] = 'Ingin menyampaikan pengaduan atau pertanyaan sendiri?';
        $lines[] = '/menu — membuka layanan seperti yang dilihat warga';
        $lines[] = '/petugas — menutupnya dan kembali ke sini';

        $categories = $recipient->categories()->pluck('name');

        $lines[] = '';
        $lines[] = $categories->isEmpty()
            ? 'Kategori yang dipegang: semua.'
            : 'Kategori yang dipegang: '.$categories->join(', ').'.';

        return implode("\n", $lines);
    }

    /** Whether a command from this destination may touch this complaint. */
    public function covers(BotRecipient $recipient, Complaint $complaint): bool
    {
        // A recipient with no categories ticked covers everything; that is how
        // a single duty officer is configured.
        if ($recipient->categories()->count() === 0) {
            return true;
        }

        return $recipient->categories()->whereKey($complaint->complaint_category_id)->exists();
    }

    private function apply(Complaint $complaint, string $status, IncomingMessage $message, BotRecipient $recipient): string
    {
        $from = $complaint->status;
        $note = $this->note($message);

        DB::transaction(function () use ($complaint, $status, $from, $note, $message, $recipient) {
            $complaint->status = $status;
            $complaint->processed_at ??= $status === 'diproses' ? now() : $complaint->processed_at;

            if ($status === 'selesai') {
                $complaint->resolved_at = now();
            }

            $complaint->save();

            // A photograph sent with /selesai is proof of the work, not a
            // second copy of the original report.
            if (filled($message->mediaPath)) {
                ComplaintAttachment::create([
                    'complaint_id' => $complaint->getKey(),
                    'kind' => $status === 'selesai' ? 'resolution' : 'report',
                    'path' => $message->mediaPath,
                    'mime' => $message->mediaMime,
                ]);
            }

            $complaint->updates()->create([
                'from_status' => $from,
                'to_status' => $status,
                'note' => $note,
                'user_id' => $recipient->user_id,
                'source' => $recipient->channel,
                'source_actor' => $recipient->name.' ('.$recipient->destination.')',
            ]);
        });

        $reply = 'Pengaduan '.$complaint->ticket.' ditandai '.$complaint->statusLabel().'.';

        if (filled($message->mediaPath)) {
            $reply .= ' Foto tindak lanjut tersimpan.';
        }

        return $reply;
    }

    /** The ticket named in the command, or the one the reply pointed at. */
    private function ticket(IncomingMessage $message): ?string
    {
        return preg_match('/\bADU-[A-Z0-9]{8}\b/i', $message->body(), $matches)
            ? strtoupper($matches[0])
            : null;
    }

    /** Whatever the officer wrote after the command and the ticket. */
    private function note(IncomingMessage $message): ?string
    {
        $note = trim(preg_replace(
            ['/^\/\S+\s*/', '/\bADU-[A-Z0-9]{8}\b/i'],
            '',
            $message->body(),
        ) ?? '');

        return $note !== '' ? $note : null;
    }
}

<?php

namespace App\Services\Bot\Actions;

use App\Models\Bot\BotRecipient;
use App\Models\Complaint;
use App\Models\ComplaintDisposition;
use App\Models\DispositionTarget;
use App\Models\OfficerTriage;
use App\Services\Bot\TemplateRenderer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

/**
 * Mengarahkan pengaduan yang bukan kewenangan dinas ini.
 *
 * Satu perintah, satu pertanyaan:
 *
 *   /bukan ADU-XXXXXXXX  →  daftar instansi  →  balas angkanya
 *
 * Sempat dirancang bertahap — kewenangan, lalu tolak-atau-arahkan, baru
 * instansinya — tetapi dua pertanyaan pertama tidak menambah apa pun:
 * kewenangannya sudah dinyatakan oleh perintah yang diketik petugas, dan
 * penolakan sudah punya perintahnya sendiri (`/tolak`).
 *
 * Yang dikirim hanya SATU pesan, dan itu kepada warga: laporannya bukan
 * kewenangan dinas ini, beserta nama dan nomor instansi yang dapat
 * dihubunginya. Bot tidak menghubungi instansi tujuan — menghubungi instansi
 * lain atas nama warga menjanjikan sesuatu yang tidak dapat dijamin dinas ini.
 */
class ComplaintTriage
{
    /** Setengah jam: cukup untuk satu laporan, tidak cukup untuk esok hari. */
    private const TIMEOUT_MINUTES = 30;

    public function __construct(
        private readonly ReporterReplier $replier,
        private readonly TemplateRenderer $renderer,
    ) {
    }

    /**
     * Menyatakan sebuah pengaduan bukan kewenangan dinas ini, dan menawarkan
     * ke mana warga sebaiknya diarahkan.
     *
     * Satu langkah, bukan tiga. Pertanyaan "apakah ini kewenangan kita?" sudah
     * terjawab oleh perintah yang diketik petugas, dan "tolak atau arahkan?"
     * mubazir karena `/tolak` sudah berdiri sendiri sebagai perintah. Yang
     * benar-benar perlu ditanyakan hanya satu: ke instansi mana.
     */
    public function start(BotRecipient $recipient, Complaint $complaint): string
    {
        $targets = DispositionTarget::active()->get();

        if ($targets->isEmpty()) {
            return 'Belum ada instansi tujuan yang terdaftar, jadi belum ada ke mana mengarahkannya.'
                ."\n\nTambahkan lebih dulu di panel: Pengaduan → Tujuan Pengarahan."
                ."\n\nBila memang tidak perlu diarahkan:\n```\n/tolak ".$complaint->ticket."\n```";
        }

        OfficerTriage::updateOrCreate(
            ['bot_recipient_id' => $recipient->getKey()],
            [
                'complaint_id' => $complaint->getKey(),
                'step' => OfficerTriage::TARGET,
                'expires_at' => now()->addMinutes(self::TIMEOUT_MINUTES),
            ],
        );

        $lines = [
            'Pengaduan *'.$complaint->ticket.'* — '.($complaint->category?->name ?? 'Tanpa kategori'),
            '',
            'Arahkan warga ke mana?',
            '',
        ];

        foreach ($targets as $index => $target) {
            $lines[] = ($index + 1).'. '.$target->name
                .($target->description ? ' — '.$target->description : '');
        }

        $lines[] = '';
        $lines[] = 'Balas angkanya, atau /batal untuk berhenti.';

        return implode("\n", $lines);
    }

    /** Triase yang sedang berjalan untuk nomor ini, bila ada. */
    public function pending(BotRecipient $recipient): ?OfficerTriage
    {
        return OfficerTriage::live()
            ->where('bot_recipient_id', $recipient->getKey())
            ->with('complaint.category')
            ->first();
    }

    public function cancel(BotRecipient $recipient): void
    {
        OfficerTriage::where('bot_recipient_id', $recipient->getKey())->delete();
    }

    /**
     * Satu jawaban bernomor.
     *
     * @return string|null null bila jawabannya bukan angka yang ditawarkan —
     *                     pemanggilnya lalu membiarkan pesan itu lewat, sebab
     *                     petugas boleh menyela triase dengan perintah lain.
     */
    public function answer(BotRecipient $recipient, OfficerTriage $triage, string $reply): ?string
    {
        return $triage->step === OfficerTriage::TARGET
            ? $this->target($recipient, $triage, trim($reply))
            : null;
    }

    private function target(BotRecipient $recipient, OfficerTriage $triage, string $choice): ?string
    {
        $targets = DispositionTarget::active()->get();
        $target = ctype_digit($choice) ? $targets->get((int) $choice - 1) : null;

        if (! $target) {
            return null;
        }

        return $this->forward($recipient, $triage, $target);
    }

    private function forward(BotRecipient $recipient, OfficerTriage $triage, DispositionTarget $target): string
    {
        $complaint = $triage->complaint;

        $values = $this->values($complaint) + [
            'target' => $target->name,
            'target_phone' => $target->phone,
            'target_contact' => $target->contactLine(),
        ];

        DB::transaction(function () use ($complaint, $recipient, $target) {
            $from = $complaint->status;
            $complaint->update(['status' => 'diteruskan']);

            ComplaintDisposition::create([
                'complaint_id' => $complaint->getKey(),
                'disposition_target_id' => $target->getKey(),
                // Disalin, bukan hanya dirujuk: instansinya dapat dihapus atau
                // berganti nomor, sementara riwayat harus tetap menjawab
                // "waktu itu dikirim ke mana".
                'target_name' => $target->name,
                'target_phone' => $target->phone,
                'source' => $recipient->channel,
                'source_actor' => $recipient->name.' ('.$recipient->destination.')',
            ]);

            $complaint->updates()->create([
                'from_status' => $from,
                'to_status' => 'diteruskan',
                'note' => 'Diarahkan ke '.$target->name.' ('.$target->phone.').',
                'user_id' => $recipient->user_id,
                'source' => $recipient->channel,
                'source_actor' => $recipient->name.' ('.$recipient->destination.')',
            ]);

            // Tidak ada apa pun yang dikirim ke instansi tujuan.
            //
            // Bot hanya memberi tahu warga ke mana ia harus menghubungi.
            // Menghubungi instansi lain atas nama warga menjanjikan sesuatu
            // yang tidak dapat dijamin dinas ini: kami tidak tahu apakah
            // pesannya dibaca, apalagi ditindaklanjuti. Memberi nomor yang
            // benar membuat warga memegang kendalinya sendiri — dan tidak
            // memerlukan biaya, template Meta, maupun jendela 24 jam.
        });

        // Satu-satunya pesan yang dikirim: kepada warga, berisi ke mana ia
        // harus menghubungi. Mengarahkan tanpa memberi tahu pelapor membuat
        // laporannya tampak hilang begitu saja.
        $told = $this->replier->send(
            $complaint,
            $this->renderer->render($target->reporterMessage(), $values),
            null,
            $recipient->user,
            $recipient->channel,
            $recipient->name.' ('.$recipient->destination.')',
        );

        $this->cancel($recipient);

        $lines = [
            'Pengaduan *'.$complaint->ticket.'* diarahkan ke *'.$target->name.'* ('.$target->phone.').',
            '',
            $told
                ? 'Pelapor sudah diberi tahu beserta nomor yang dapat dihubunginya.'
                : 'Pelapor tidak dapat dihubungi — laporan ini tidak berasal dari chat.',
        ];

        return implode("\n", $lines);
    }

    /** @return array<string, string> */
    private function values(Complaint $complaint): array
    {
        return [
            'ticket' => $complaint->ticket,
            'category' => $complaint->category?->name ?? 'Tanpa kategori',
            'description' => (string) $complaint->description,
            'date' => $complaint->created_at?->translatedFormat('d F Y H:i') ?? '',
            'reporter' => (string) ($complaint->reporter_name ?: '-'),
            'phone' => (string) ($complaint->reporter_phone ?: '-'),
            'location' => $complaint->hasLocation()
                ? 'Lokasi: https://www.google.com/maps?q='.$complaint->latitude.','.$complaint->longitude
                : 'Lokasi tidak disertakan.',
            'photos' => $this->photoLinks($complaint),
        ];
    }

    /**
     * Tautan foto bukti, bertanda tangan dan berbatas waktu.
     *
     * Satu-satunya jalan foto sampai ke instansi tujuan pada penerusan lewat
     * `wa.me`, yang hanya mampu membawa teks. Berlaku juga untuk kanal lain:
     * pesan yang memuat tautan tetap dapat dibuka meski gambarnya tidak ikut
     * terlampir.
     *
     * Berbatas waktu karena ini foto rumah dan pekarangan orang — tautan yang
     * berlaku selamanya akan tetap terbuka di grup WhatsApp bertahun kemudian.
     */
    private function photoLinks(Complaint $complaint): string
    {
        $photos = $complaint->evidence()->orderBy('id')->get();

        if ($photos->isEmpty()) {
            return 'Tidak ada foto yang dilampirkan pelapor.';
        }

        $expiry = now()->addDays((int) config('complaints.evidence_link_days', 14));

        $lines = [$photos->count() === 1 ? 'Foto pelapor:' : 'Foto pelapor ('.$photos->count().'):'];

        foreach ($photos as $photo) {
            $lines[] = URL::temporarySignedRoute('public.evidence.show', $expiry, $photo);
        }

        $lines[] = '';
        $lines[] = 'Tautan foto berlaku sampai '.$expiry->translatedFormat('d F Y').'.';

        return implode("\n", $lines);
    }
}

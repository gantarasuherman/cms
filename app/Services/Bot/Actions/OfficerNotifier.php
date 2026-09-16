<?php

namespace App\Services\Bot\Actions;

use App\Models\Bot\BotRecipient;
use App\Models\Complaint;
use Illuminate\Support\Facades\DB;

/**
 * Queues a note to whoever covers this complaint's category.
 *
 * Queued, not sent: this runs inside the request that files the complaint, and
 * a platform being briefly unreachable must not cost the reporter their report.
 * The outbox is drained separately and retried.
 *
 * Nobody covering the category means nobody is told — deliberately. Falling
 * back to "tell everyone" would quietly undo the assignment an administrator
 * set up, and the complaint is still on the admin screen either way.
 */
class OfficerNotifier
{
    public function notify(Complaint $complaint): int
    {
        $recipients = BotRecipient::forCategory($complaint->complaint_category_id)->get();

        if ($recipients->isEmpty()) {
            return 0;
        }

        $body = $this->compose($complaint);

        // Foto pelapor ikut dikirim, bukan hanya disebut ada.
        //
        // Petugas membaca kabar ini di ponsel, sering di jalan. Kalimat "2 foto
        // terlampir pada panel admin" memaksa mereka membuka laptop untuk
        // menjawab pertanyaan yang paling menentukan — seberapa parah, dan
        // perlu bawa apa. Satu foto pertama biasanya sudah menjawabnya.
        //
        // Hanya yang pertama: mengantre satu baris per foto membuat sepuluh
        // pesan beruntun masuk ke grup petugas untuk satu laporan, dan grup
        // yang berisik adalah grup yang berhenti dibaca. Sisanya tetap
        // disebutkan jumlahnya, dan seluruhnya ada di panel.
        $photo = $complaint->evidence()->orderBy('id')->first();
        $queued = 0;

        DB::transaction(function () use ($recipients, $complaint, $body, $photo, &$queued) {
            foreach ($recipients as $recipient) {
                DB::table('bot_outbox')->insert([
                    'channel' => $recipient->channel,
                    'destination' => $recipient->destination,
                    'type' => $photo ? 'image' : 'text',
                    'body' => $body,
                    'media_path' => $photo?->path,
                    'payload' => json_encode(['complaint_id' => $complaint->getKey()]),
                    'status' => 'pending',
                    'available_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $queued++;
            }
        });

        return $queued;
    }

    private function compose(Complaint $complaint): string
    {
        $lines = [
            'PENGADUAN BARU',
            '',
            'Tiket: '.$complaint->ticket,
            'Kategori: '.($complaint->category?->name ?? 'Tanpa kategori'),
            'Kanal: '.$complaint->channel,
            '',
            $complaint->description,
        ];

        if ($complaint->hasLocation()) {
            // A plain maps link rather than a pin attachment: it works the same
            // in a Telegram group and a WhatsApp message, and it is readable.
            $lines[] = '';
            $lines[] = 'Lokasi: https://www.google.com/maps?q='.$complaint->latitude.','.$complaint->longitude;
        }

        $photos = $complaint->evidence()->count();

        if ($photos === 1) {
            // Fotonya menyertai pesan ini, jadi tidak perlu dikatakan apa-apa
            // tentang panel — mengarahkan orang ke tempat lain untuk melihat
            // sesuatu yang sudah ada di tangannya hanya membuang waktunya.
            $lines[] = '';
            $lines[] = 'Foto pelapor terlampir.';
        } elseif ($photos > 1) {
            $lines[] = '';
            $lines[] = 'Foto pertama dari '.$photos.' terlampir; selebihnya pada panel admin.';
        }

        /*
         | Tiap perintah dalam blok kodenya sendiri.
         |
         | Telegram menyalin seluruh isi sebuah blok kode dengan satu ketukan,
         | jadi satu blok berisi tiga perintah akan menyalin ketiganya
         | sekaligus — tidak berguna. Satu blok per perintah membuat yang
         | disalin persis yang ditekan, tinggal ditempel dan dikirim.
         |
         | Bentuk tiga-petik dipilih karena bekerja di kedua kanal: Telegram
         | menjadikannya blok kode yang dapat disalin, WhatsApp menjadikannya
         | monospace yang mudah ditekan-lama. Satu petik hanya dikenali
         | Telegram, dan akan tampil apa adanya di WhatsApp.
         */
        $lines[] = '';
        $lines[] = 'Bukan kewenangan kita — arahkan ke instansi lain:';
        $lines[] = $this->command('/bukan '.$complaint->ticket);
        $lines[] = 'Sedang dikerjakan:';
        $lines[] = $this->command('/proses '.$complaint->ticket);
        $lines[] = 'Sudah ditangani:';
        $lines[] = $this->command('/selesai '.$complaint->ticket);

        return implode("\n", $lines);
    }

    /** Satu perintah, siap disalin dengan sekali ketuk. */
    private function command(string $text): string
    {
        return "```\n".$text."\n```";
    }
}

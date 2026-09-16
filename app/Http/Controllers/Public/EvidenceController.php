<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\ComplaintAttachment;
use App\Services\Media\MediaService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Foto bukti yang dapat dibuka instansi tujuan penerusan.
 *
 * Satu-satunya jalan foto sampai ke instansi yang dituju lewat tautan
 * WhatsApp: `wa.me` hanya dapat membawa teks, jadi gambarnya harus berupa
 * alamat yang dapat dibuka penerimanya.
 *
 * Tiga hal menjaga alamat itu tetap sempit:
 *
 * - **Ditandatangani.** Tanpa tanda tangan yang sah, id pada URL tidak berarti
 *   apa-apa — jadi menebak-nebak angka berikutnya tidak membuka foto siapa pun.
 * - **Kedaluwarsa.** Ini foto rumah dan pekarangan orang; sebuah tautan yang
 *   berlaku selamanya akan tetap terbuka di grup WhatsApp bertahun kemudian.
 * - **Hanya bukti pelapor.** Foto tindak lanjut petugas bukan bagian dari
 *   berkas yang diteruskan.
 */
class EvidenceController extends Controller
{
    public function __invoke(ComplaintAttachment $attachment): StreamedResponse
    {
        abort_unless($attachment->kind === 'report', 404);
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        return Storage::disk('local')->response(
            $attachment->path,
            basename($attachment->path),
            [
                'Content-Type' => MediaService::mimeFor($attachment->path, $attachment->mime),
                // Tidak diindeks: tautannya memang untuk satu penerima, bukan
                // untuk ditemukan orang lain.
                'X-Robots-Tag' => 'noindex, nofollow',
            ],
        );
    }
}

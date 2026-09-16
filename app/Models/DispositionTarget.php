<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Instansi tujuan penerusan pengaduan.
 *
 * Yang disimpan hanyalah nama, nomor, dan dua kalimat. Sisanya — kapan sebuah
 * laporan diteruskan, oleh siapa — adalah riwayat pengaduan itu sendiri.
 */
class DispositionTarget extends Model
{
    /**
     * Lewat mana penerusan sampai ke instansi tujuan.
     *
     * `link` adalah bawaan dan satu-satunya yang pasti tidak berbiaya: bot
     * menyiapkan kalimatnya, petugas menekan tautannya dan mengirim dari
     * WhatsApp miliknya sendiri.
     */
    public const CHANNELS = [
        'link' => 'Tautan WhatsApp — dikirim petugas, tanpa biaya',
        'telegram' => 'Telegram — dikirim bot, tanpa biaya',
        'whatsapp' => 'WhatsApp Cloud API — dikirim bot, berbayar',
    ];

    protected $fillable = [
        'name', 'slug', 'phone', 'channel', 'template_name', 'template_language',
        'contact_person', 'description',
        'target_template', 'reporter_template', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function dispositions(): HasMany
    {
        return $this->hasMany(ComplaintDisposition::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }

    public function channelLabel(): string
    {
        return self::CHANNELS[$this->channel] ?? $this->channel;
    }

    /** Apakah bot yang mengirimkannya, atau petugas lewat tautan. */
    public function sentByBot(): bool
    {
        return in_array($this->channel, ['telegram', 'whatsapp'], true);
    }

    /**
     * Isian template untuk satu pengaduan, atau null bila tidak memakainya.
     *
     * Tiga parameter saja, urutannya tetap: tiket, jenis, uraian. Sebuah
     * template yang sudah disetujui Meta tidak dapat diubah bentuknya tanpa
     * pengajuan baru, jadi bentuk yang sedikit dan tetap jauh lebih tahan
     * daripada yang membagi laporan ke banyak kotak.
     */
    public function templatePayload(Complaint $complaint): ?string
    {
        if ($this->channel !== 'whatsapp' || blank($this->template_name)) {
            return null;
        }

        return json_encode([
            'name' => $this->template_name,
            'language' => $this->template_language ?: 'id',
            'parameters' => [
                $complaint->ticket,
                $complaint->category?->name ?? 'Tanpa kategori',
                // Dipotong: Meta menolak parameter yang memuat baris baru atau
                // terlalu panjang, dan seluruh pesan gagal terkirim karenanya.
                str(preg_replace('/\s+/u', ' ', (string) $complaint->description))->limit(600)->value(),
            ],
        ]);
    }

    /**
     * Tautan WhatsApp berisi pesan yang sudah tersusun.
     *
     * Ditekan petugas dan terkirim dari WhatsApp miliknya sendiri, jadi tidak
     * melewati Cloud API sama sekali — tidak ada biaya, tidak ada jendela 24
     * jam, dan tidak ada template yang harus disetujui lebih dulu.
     */
    public function shareLink(string $message): string
    {
        return 'https://wa.me/'.preg_replace('/\D/', '', $this->phone)
            .'?text='.rawurlencode($message);
    }

    /**
     * Kalimat untuk warga yang melapor.
     *
     * Satu-satunya pesan yang dikirim pada pengarahan. Bot tidak menghubungi
     * instansi tujuan: yang diberikan kepada warga adalah nama dan nomor yang
     * dapat ia hubungi sendiri.
     *
     * Templat kosong bukan kesalahan — bunyi bawaannya sudah memuat yang
     * dibutuhkan. Templat ada supaya kalimatnya dapat disesuaikan, bukan
     * supaya setiap tujuan wajib dikarang satu per satu.
     */
    public function reporterMessage(): string
    {
        return trim((string) $this->reporter_template) ?: implode("\n", [
            'Kabar pengaduan Anda.',
            '',
            'Tiket: *{ticket}*',
            '',
            'Setelah ditinjau, laporan ini bukan kewenangan {site_name} '
                .'melainkan *{target}*.',
            '',
            'Silakan menghubungi mereka langsung:',
            '{target_contact}',
            '',
            'Mohon maaf atas ketidaknyamanannya, dan terima kasih sudah melapor.',
        ]);
    }

    /** Nama penerima dan nomornya, seperti dibacakan kepada warga. */
    public function contactLine(): string
    {
        $lines = [$this->name.' — '.$this->phone];

        if (filled($this->contact_person)) {
            $lines[] = 'Narahubung: '.$this->contact_person;
        }

        return implode("\n", $lines);
    }
}

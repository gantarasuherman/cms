<?php

namespace App\Models;

use App\Models\Bot\BotRecipient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Di mana seorang petugas berada dalam triase yang sedang berjalan.
 *
 * Petugas tidak memakai alur percakapan warga — mereka memakai perintah — jadi
 * triase bermenu ini menyimpan tempatnya sendiri. Satu baris per nomor
 * petugas: satu laporan diselesaikan lebih dulu sebelum yang berikutnya,
 * sehingga tidak pernah ada pertanyaan "angka 2 ini untuk laporan yang mana".
 */
class OfficerTriage extends Model
{
    /** Apakah laporan ini kewenangan kami? */
    public const AUTHORITY = 'authority';

    /** Bukan kewenangan kami: ditolak, atau diteruskan? */
    public const DECISION = 'decision';

    /** Diteruskan ke instansi yang mana? */
    public const TARGET = 'target';

    protected $fillable = ['bot_recipient_id', 'complaint_id', 'step', 'expires_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(BotRecipient::class, 'bot_recipient_id');
    }

    /**
     * Triase yang belum kedaluwarsa.
     *
     * Ada batas waktunya karena sebuah menu yang ditinggalkan setengah jalan
     * tidak boleh menyandera angka yang diketik petugas esok hari — "2" pada
     * hari berikutnya hampir pasti dimaksudkan untuk hal lain.
     */
    public function scopeLive(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->whereNull('expires_at')
            ->orWhere('expires_at', '>', now()));
    }
}

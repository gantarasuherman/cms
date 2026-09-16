<?php

namespace App\Models;

use App\Models\Bot\BotRecipient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ComplaintCategory extends Model
{
    /**
     * Warna pin peta, dalam urutan tetap.
     *
     * Diambil dari palet kategorikal yang sudah divalidasi — bukan warna yang
     * dikarang per kategori. Urutannya tetap: jenis kelima selalu mendapat
     * slot kelima, sehingga menonaktifkan satu jenis tidak mengecat ulang
     * seluruh peta dan membuat "yang biru" berarti hal lain esok harinya.
     *
     * Empat slot pertama dapat dibedakan dengan aman meski oleh mata yang
     * tidak membedakan warna tertentu. Lebih dari itu tidak — karena itu pin
     * juga membawa ikon jenisnya, dan popup menyebut namanya. Warna di sini
     * mempercepat pembacaan, bukan satu-satunya penanda.
     *
     * @var array<int, string>
     */
    public const PIN_COLORS = [
        '#2a78d6', // biru
        '#eb6834', // oranye
        '#1baf7a', // toska
        '#4a3aa7', // ungu
        '#eda100', // kuning
        '#e34948', // merah
        '#008300', // hijau
        '#e87ba4', // merah muda
    ];

    /** Dipakai bila jenisnya melebihi panjang palet. */
    public const PIN_FALLBACK = '#64748b';

    protected $fillable = [
        'name', 'slug', 'icon', 'color', 'description', 'contact_name', 'contact_phone',
        'requires_photo', 'requires_location', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'requires_photo' => 'boolean',
            'requires_location' => 'boolean',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class);
    }

    public function recipients(): BelongsToMany
    {
        return $this->belongsToMany(BotRecipient::class, 'bot_recipient_categories');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    /**
     * Warna pin jenis ini: pilihan administrator, atau bawaan menurut urutan.
     *
     * Posisinya dihitung dari seluruh jenis yang tersimpan, bukan hanya yang
     * aktif — menonaktifkan satu jenis tidak boleh menggeser warna jenis
     * sesudahnya pada peta yang sedang dibaca orang.
     */
    public function pinColor(): string
    {
        if (filled($this->color)) {
            return $this->color;
        }

        $position = static::orderBy('sort_order')->orderBy('id')->pluck('id')->search($this->getKey());

        if ($position === false || $position >= count(self::PIN_COLORS)) {
            return self::PIN_FALLBACK;
        }

        return self::PIN_COLORS[$position];
    }

    /** What the bot must collect before it will file a complaint here. */
    /** Apakah bidang ini punya nomor yang boleh diberikan kepada warga. */
    public function hasContact(): bool
    {
        return filled($this->contact_phone);
    }

    /**
     * Nomor bidang ini sebagaimana dibacakan kepada warga.
     *
     * Nomornya ditulis apa adanya DAN sebagai tautan wa.me. Nomor telanjang
     * dapat disalin orang yang membaca dari layar terkunci atau mencatatnya di
     * kertas; tautannya menghemat satu langkah bagi yang lain. Menyisakan
     * salah satunya saja berarti memilihkan salah satu kelompok itu.
     */
    public function contactLine(): ?string
    {
        if (! $this->hasContact()) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $this->contact_phone);

        return implode("\n", array_filter([
            $this->contact_name ?: $this->name,
            $this->contact_phone,
            $digits ? 'https://wa.me/'.$digits : null,
        ]));
    }

    public function evidenceRequired(): array
    {
        return array_keys(array_filter([
            'image' => $this->requires_photo,
            'location' => $this->requires_location,
        ]));
    }
}

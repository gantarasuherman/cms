<?php

namespace App\Models\Bot;

use App\Models\ComplaintCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A number or group that hears about new complaints, and may be allowed to
 * command the ones it hears about.
 */
class BotRecipient extends Model
{
    protected $fillable = ['name', 'channel', 'destination', 'is_active', 'can_command', 'user_id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'can_command' => 'boolean',
        ];
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(ComplaintCategory::class, 'bot_recipient_categories');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Recipients who asked to hear about this category.
     *
     * Tidak mencentang satu kategori pun berarti memegang semuanya — petugas
     * piket, dan itu pula yang dikatakan formulirnya.
     *
     * Sebelumnya baris tanpa kategori tidak pernah cocok dengan apa pun,
     * sementara CommandHandler::covers() sudah memperlakukannya sebagai
     * "semua". Akibatnya sebuah grup petugas boleh menutup pengaduan apa saja
     * tetapi tidak pernah dikabari satu pun — terdaftar, aktif, dan diam.
     * Tidak ada yang terlihat salah di layar mana pun.
     */
    public function scopeForCategory(Builder $query, ?int $categoryId): Builder
    {
        return $query->active()->when(
            $categoryId,
            // Dikurung. Tanpa kurung, `is_active AND punya-kategori OR
            // tanpa-kategori` dibaca SQL sebagai `(is_active AND
            // punya-kategori) OR (tanpa-kategori)` — dan petugas yang sudah
            // dinonaktifkan ikut dikabari lagi.
            fn (Builder $q) => $q->where(fn (Builder $group) => $group
                ->whereHas('categories', fn (Builder $c) => $c->whereKey($categoryId))
                ->orWhereDoesntHave('categories')),
        );
    }

    public function channelLabel(): string
    {
        return BotChannel::KEYS[$this->channel] ?? $this->channel;
    }
}

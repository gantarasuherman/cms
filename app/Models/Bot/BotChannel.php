<?php

namespace App\Models\Bot;

use App\Models\Bot\BotFlow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BotChannel extends Model
{
    public const WHATSAPP = 'whatsapp';

    public const TELEGRAM = 'telegram';

    /** @var array<string, string> */
    public const KEYS = [
        self::WHATSAPP => 'WhatsApp',
        self::TELEGRAM => 'Telegram',
    ];

    /**
     * The secrets each channel needs, and whether each one is a secret.
     *
     * A field marked secret is never echoed back to a form, never written to
     * the audit log, and never printed anywhere — only the last few characters
     * are shown, which is enough to tell two keys apart without revealing one.
     *
     * @var array<string, array<string, array{label: string, secret: bool, hint: string}>>
     */
    public const CREDENTIAL_FIELDS = [
        self::WHATSAPP => [
            'phone_number_id' => ['label' => 'Phone Number ID', 'secret' => false, 'hint' => 'Dari WhatsApp Manager pada akun Meta Business Anda.'],
            'business_account_id' => ['label' => 'Business Account ID', 'secret' => false, 'hint' => 'Opsional; dipakai sebagian panggilan lanjutan.'],
            'app_id' => ['label' => 'App ID', 'secret' => false, 'hint' => 'Dari Meta → App → Settings → Basic. Diisi agar alamat webhook didaftarkan sendiri setiap layanan naik; dikosongkan berarti alamatnya ditempel manual di dasbor Meta.'],
            'token' => ['label' => 'Access Token', 'secret' => true, 'hint' => 'Token sistem berumur panjang dengan izin whatsapp_business_messaging.'],
            'verify_token' => ['label' => 'Verify Token', 'secret' => true, 'hint' => 'Kata apa pun yang Anda tentukan sendiri; Meta mengembalikannya saat mendaftarkan webhook.'],
            'app_secret' => ['label' => 'App Secret', 'secret' => true, 'hint' => 'Dipakai memverifikasi tanda tangan setiap webhook. Tanpa ini webhook ditolak.'],
        ],
        self::TELEGRAM => [
            'token' => ['label' => 'Bot Token', 'secret' => true, 'hint' => 'Dari @BotFather, berbentuk 123456:ABC-DEF…'],
            'secret_token' => ['label' => 'Webhook Secret', 'secret' => true, 'hint' => 'Opsional tapi dianjurkan: Telegram tidak menandatangani webhook-nya.'],
        ],
    ];

    protected $fillable = ['key', 'name', 'is_active', 'bot_flow_id', 'settings', 'credentials', 'verified_at', 'last_error'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'settings' => 'array',
            // Encrypted at rest: anybody holding the WhatsApp token can send
            // messages as the institution.
            'credentials' => 'encrypted:array',
            'verified_at' => 'datetime',
        ];
    }

    /**
     * One credential, from the database first and the environment second.
     *
     * The environment remains valid so a deployment that already sets these
     * keeps working; the panel simply takes precedence once filled in.
     */
    public function credential(string $field): ?string
    {
        $stored = data_get($this->credentials, $field);

        return filled($stored) ? $stored : (config("bot.{$this->key}.{$field}") ?: null);
    }

    /** Whether a value came from the panel rather than the server's environment. */
    public function credentialIsStored(string $field): bool
    {
        return filled(data_get($this->credentials, $field));
    }

    /** Enough of a key to recognise it, never enough to use it. */
    public function credentialHint(string $field): ?string
    {
        $value = (string) $this->credential($field);

        if ($value === '') {
            return null;
        }

        return str_repeat('•', 8).mb_substr($value, -4);
    }

    /** @return array<string, array{label: string, secret: bool, hint: string}> */
    public function credentialFields(): array
    {
        return self::CREDENTIAL_FIELDS[$this->key] ?? [];
    }

    public function flow(): BelongsTo
    {
        return $this->belongsTo(BotFlow::class, 'bot_flow_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(BotContact::class);
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    /**
     * Whether the channel can actually send.
     *
     * Being switched on is not enough: the token lives in the environment, so a
     * channel an administrator enabled before anyone configured the server is
     * active and mute. This is what the admin screen reports.
     */
    public function credentialsPresent(): bool
    {
        return match ($this->key) {
            self::WHATSAPP => filled($this->credential('token')) && filled($this->credential('phone_number_id')),
            self::TELEGRAM => filled($this->credential('token')),
            default => false,
        };
    }

    /** What is still missing before this channel can send anything. */
    public function missingCredentials(): array
    {
        $required = match ($this->key) {
            self::WHATSAPP => ['phone_number_id', 'token'],
            self::TELEGRAM => ['token'],
            default => [],
        };

        return array_values(array_filter(
            $required,
            fn (string $field) => blank($this->credential($field)),
        ));
    }

    public function isReady(): bool
    {
        return $this->is_active && $this->credentialsPresent();
    }
}

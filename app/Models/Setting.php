<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['group', 'key', 'value', 'type'];

    /** Casts the stored string into the PHP type declared by the `type` column. */
    private function decrypted(): ?string
    {
        if (blank($this->value)) {
            return null;
        }

        try {
            return \Illuminate\Support\Facades\Crypt::decryptString((string) $this->value);
        } catch (\Throwable) {
            return null;
        }
    }

    public function typedValue(): mixed
    {
        return match ($this->type) {
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $this->value,
            'json' => json_decode((string) $this->value, true) ?? [],
            // An API key is the one setting worth protecting at rest: it is
            // readable in every database backup otherwise. A value that fails
            // to decrypt returns null rather than throwing, so one bad row
            // cannot take a settings screen down.
            'encrypted' => $this->decrypted(),
            default => $this->value,
        };
    }
}


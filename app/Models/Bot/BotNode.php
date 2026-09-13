<?php

namespace App\Models\Bot;

use App\Support\BotNodes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BotNode extends Model
{
    protected $fillable = [
        'bot_flow_id', 'key', 'type', 'label', 'config', 'position_x', 'position_y',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'position_x' => 'integer',
            'position_y' => 'integer',
        ];
    }

    public function flow(): BelongsTo
    {
        return $this->belongsTo(BotFlow::class, 'bot_flow_id');
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->config, $key, $default);
    }

    public function typeLabel(): string
    {
        return BotNodes::types()[$this->type]['label'] ?? $this->type;
    }

    /** @return array<int, array{value: string, label: string}> */
    public function options(): array
    {
        return array_values(array_filter(
            (array) $this->setting('options', []),
            fn ($option) => is_array($option) && filled($option['label'] ?? null),
        ));
    }
}

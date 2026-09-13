<?php

namespace App\Models\Bot;

use App\Support\BotNodes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BotDataSource extends Model
{
    protected $fillable = [
        'name', 'slug', 'source', 'limit', 'list_template', 'detail_template', 'filters', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'limit' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function sourceLabel(): string
    {
        return BotNodes::dataSources()[$this->source] ?? $this->source;
    }
}

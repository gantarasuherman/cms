<?php

namespace App\Models\Bot;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BotFlow extends Model
{
    protected $fillable = [
        'name', 'slug', 'description', 'is_active', 'is_default', 'version', 'published_at', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'version' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    public function nodes(): HasMany
    {
        return $this->hasMany(BotNode::class)->orderBy('id');
    }

    public function edges(): HasMany
    {
        return $this->hasMany(BotEdge::class)->orderBy('sort_order');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(BotFlowVersion::class);
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function startNode(): ?BotNode
    {
        return $this->nodes->firstWhere('type', 'start');
    }

    /**
     * The whole graph as the engine and the editor both consume it.
     *
     * One shape, one place: the editor loads this, the published snapshot
     * stores it, and the Python runtime is handed it. Three readers of three
     * different shapes is how a flow ends up behaving differently from how it
     * was drawn.
     *
     * @return array<string, mixed>
     */
    public function toGraph(): array
    {
        return [
            'flow' => [
                'slug' => $this->slug,
                'name' => $this->name,
                'version' => $this->version,
            ],
            'nodes' => $this->nodes->map(fn (BotNode $node) => [
                'key' => $node->key,
                'type' => $node->type,
                'label' => $node->label,
                'config' => $node->config ?? [],
                'position' => ['x' => $node->position_x, 'y' => $node->position_y],
            ])->values()->all(),
            'edges' => $this->edges->map(fn (BotEdge $edge) => [
                'from' => $edge->from_node,
                'to' => $edge->to_node,
                'condition' => $edge->condition,
                'label' => $edge->label,
            ])->values()->all(),
        ];
    }
}

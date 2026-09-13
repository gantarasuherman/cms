<?php

namespace App\Services\Bot;

use App\Models\Bot\BotFlow;
use App\Models\Bot\BotNode;
use App\Support\BotNodes;

/**
 * Reads a flow back and reports what will not work.
 *
 * Separate from validation on purpose: a half-drawn flow must still be
 * saveable, or an editor could never stop mid-thought. These are warnings the
 * editor shows and publishing refuses on — the difference between "you cannot
 * write this down" and "this is not ready for the public".
 */
class FlowInspector
{
    /** @return array<int, array{level: string, node: ?string, message: string}> */
    public function inspect(BotFlow $flow): array
    {
        $nodes = $flow->nodes;
        $edges = $flow->edges;
        $problems = [];

        $start = $nodes->firstWhere('type', BotNodes::START);

        if (! $start) {
            return [['level' => 'error', 'node' => null, 'message' => 'Alur belum punya node Mulai.']];
        }

        foreach ($this->unreachable($nodes, $edges, $start->key) as $key) {
            $problems[] = [
                'level' => 'error',
                'node' => $key,
                'message' => 'Tidak dapat dicapai dari Mulai — tidak akan pernah dilihat siapa pun.',
            ];
        }

        foreach ($nodes as $node) {
            $out = $edges->where('from_node', $node->key);

            if ($node->type === BotNodes::END) {
                continue;
            }

            if ($out->isEmpty()) {
                $problems[] = [
                    'level' => 'error',
                    'node' => $node->key,
                    'message' => 'Tidak menuju ke mana pun; percakapan berhenti di sini tanpa penutup.',
                ];
                continue;
            }

            if ($node->type === BotNodes::MENU) {
                $problems = array_merge($problems, $this->menuProblems($node, $out));
            }

            if ($node->type === BotNodes::INPUT && ! $out->contains('condition', 'valid')) {
                $problems[] = [
                    'level' => 'error',
                    'node' => $node->key,
                    'message' => 'Tidak punya jalur untuk jawaban yang benar (kondisi "valid").',
                ];
            }

            if (in_array($node->type, [BotNodes::MENU, BotNodes::INPUT, BotNodes::DATA_SOURCE], true)
                && ! $out->contains('condition', 'exhausted')
                && ($node->setting('on_invalid', 'repeat') === 'repeat')) {
                $problems[] = [
                    'level' => 'warning',
                    'node' => $node->key,
                    // Otherwise somebody who cannot answer is asked the same
                    // question forever with no way out but the timeout.
                    'message' => 'Tidak ada jalan keluar bila pengguna gagal berulang kali. Tambahkan sambungan "exhausted".',
                ];
            }
        }

        if ($nodes->where('type', BotNodes::END)->isEmpty()) {
            $problems[] = ['level' => 'warning', 'node' => null, 'message' => 'Alur belum punya node Selesai.'];
        }

        return $problems;
    }

    public function publishable(BotFlow $flow): bool
    {
        return collect($this->inspect($flow))->where('level', 'error')->isEmpty();
    }

    /** @return array<int, array{level: string, node: ?string, message: string}> */
    private function menuProblems(BotNode $node, $out): array
    {
        // A menu filled from a table is routed by one edge, so its options
        // cannot be checked one by one.
        if ($node->setting('options_from')) {
            return $out->contains('condition', 'category')
                ? []
                : [['level' => 'error', 'node' => $node->key, 'message' => 'Menu dari tabel butuh sambungan dengan kondisi "category".']];
        }

        $problems = [];

        foreach ($node->options() as $option) {
            if (! $out->contains('condition', (string) $option['value'])) {
                $problems[] = [
                    'level' => 'error',
                    'node' => $node->key,
                    'message' => 'Pilihan "'.$option['label'].'" tidak mengarah ke mana pun.',
                ];
            }
        }

        return $problems;
    }

    /** @return array<int, string> */
    private function unreachable($nodes, $edges, string $start): array
    {
        $adjacency = $edges->groupBy('from_node');
        $seen = [];
        $queue = [$start];

        while ($queue) {
            $key = array_shift($queue);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;

            foreach ($adjacency->get($key, collect()) as $edge) {
                $queue[] = $edge->to_node;
            }
        }

        return $nodes->pluck('key')->reject(fn ($key) => isset($seen[$key]))->values()->all();
    }
}

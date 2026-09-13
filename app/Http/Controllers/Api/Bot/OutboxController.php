<?php

namespace App\Http\Controllers\Api\Bot;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Messages the system wants sent without anybody having asked for them —
 * chiefly the notification to officers when a complaint arrives.
 *
 * The bot service pulls rather than Laravel pushing, so the two never need to
 * reach each other in both directions and a bot that was down simply collects
 * what it missed when it comes back.
 *
 * Claiming is a conditional update rather than a read-then-write: two workers
 * pulling at once must not both send the same notification.
 */
class OutboxController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    public function pull(Request $request): JsonResponse
    {
        $data = $request->validate([
            'channel' => ['nullable', 'string', 'max:16'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $limit = (int) ($data['limit'] ?? 20);

        $ids = DB::table('bot_outbox')
            ->where('status', 'pending')
            ->where('attempts', '<', self::MAX_ATTEMPTS)
            ->when($data['channel'] ?? null, fn ($query, $channel) => $query->where('channel', $channel))
            ->where(fn ($query) => $query->whereNull('available_at')->orWhere('available_at', '<=', now()))
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        if ($ids->isEmpty()) {
            return response()->json(['messages' => []]);
        }

        // Claimed by moving them out of `pending` in the same statement that
        // selects them, so a second worker cannot pick up the same rows.
        DB::table('bot_outbox')->whereIn('id', $ids)->where('status', 'pending')->update([
            'status' => 'sending',
            'attempts' => DB::raw('attempts + 1'),
            'updated_at' => now(),
        ]);

        $rows = DB::table('bot_outbox')->whereIn('id', $ids)->where('status', 'sending')->get();

        return response()->json([
            'messages' => $rows->map(fn ($row) => [
                'id' => $row->id,
                'channel' => $row->channel,
                'destination' => $row->destination,
                'type' => $row->type,
                'body' => $row->body,
                'media_path' => $row->media_path,
            ])->values(),
        ]);
    }

    public function report(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => ['required', 'integer'],
            'status' => ['required', 'string', 'in:sent,failed'],
            'error' => ['nullable', 'string', 'max:500'],
        ]);

        $row = DB::table('bot_outbox')->where('id', $data['id'])->first();

        if (! $row) {
            return response()->json(['status' => 'unknown'], 404);
        }

        if ($data['status'] === 'sent') {
            DB::table('bot_outbox')->where('id', $row->id)->update([
                'status' => 'sent',
                'sent_at' => now(),
                'last_error' => null,
                'updated_at' => now(),
            ]);

            return response()->json(['status' => 'sent']);
        }

        // Back to pending with a widening delay, until the attempt cap. Giving
        // up quietly would lose the notification; retrying forever would hammer
        // a platform that is refusing for a reason.
        $exhausted = $row->attempts >= self::MAX_ATTEMPTS;

        DB::table('bot_outbox')->where('id', $row->id)->update([
            'status' => $exhausted ? 'failed' : 'pending',
            'available_at' => $exhausted ? null : now()->addMinutes(2 ** min((int) $row->attempts, 6)),
            'last_error' => $data['error'] ?? 'Pengiriman gagal.',
            'updated_at' => now(),
        ]);

        return response()->json(['status' => $exhausted ? 'failed' : 'retry']);
    }
}

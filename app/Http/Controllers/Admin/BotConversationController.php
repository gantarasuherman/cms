<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bot\BotChannel;
use App\Models\Bot\BotConversation;
use App\Models\Bot\BotContact;
use App\Models\Bot\BotMessage;
use App\Services\Media\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Yajra\DataTables\Facades\DataTables;

class BotConversationController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', BotConversation::class);

        return view('admin.bot.conversations.index', [
            'channels' => BotChannel::KEYS,
            'statuses' => [
                'active' => 'Berlangsung',
                'completed' => 'Selesai',
                'expired' => 'Kedaluwarsa',
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', BotConversation::class);

        // `select()` before `withCount()`, never after: it replaces the whole
        // select list, which wipes the count's subquery and leaves the column
        // blank on screen.
        $query = BotConversation::query()
            ->select('bot_conversations.*')
            ->with(['channel', 'contact', 'flow'])
            ->withCount('messages');

        if ($request->filled('channel')) {
            $query->whereHas('channel', fn ($q) => $q->where('key', $request->string('channel')->toString()));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->date('to'));
        }

        return DataTables::eloquent($query)
            ->addColumn('contact', fn (BotConversation $c) => e($c->contact?->displayName() ?? '—'))
            ->addColumn('channel_name', fn (BotConversation $c) => e($c->channel?->name ?? '—'))
            ->addColumn('last_message', fn (BotConversation $c) => e(Str::limit(
                (string) $c->messages()->latest('id')->value('body'), 60,
            ) ?: '—'))
            ->addColumn('status_badge', fn (BotConversation $c) => view('admin.bot.conversations.partials.status', ['conversation' => $c])->render())
            ->editColumn('last_message_at', fn (BotConversation $c) => $c->last_message_at?->diffForHumans() ?? '—')
            ->addColumn('actions', fn (BotConversation $c) => view('admin.bot.conversations.partials.actions', ['conversation' => $c])->render())
            ->rawColumns(['status_badge', 'actions'])
            ->toJson();
    }

    public function show(BotConversation $conversation): View
    {
        $this->authorize('view', $conversation);

        return view('admin.bot.conversations.show', [
            'conversation' => $conversation->load(['channel', 'contact', 'flow', 'messages']),
        ]);
    }

    /** A contact's profile picture, behind the same permission as the transcript. */
    public function avatar(BotContact $contact): StreamedResponse
    {
        $this->authorize('viewAny', BotConversation::class);

        abort_if(blank($contact->avatar_path), 404);
        abort_unless(Storage::disk('local')->exists($contact->avatar_path), 404);

        return Storage::disk('local')->response($contact->avatar_path, 'avatar.jpg', [
            'Content-Type' => 'image/jpeg',
            // Nobody published this picture, so it must not be cached by
            // anything between here and the operator's screen.
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    /**
     * Serves a picture or file somebody sent into a chat.
     *
     * Chat media sits on the private disk and is reached only here, behind the
     * same permission as the transcript itself. These are photographs people
     * sent about their own street; a guessable public URL would be a leak.
     */
    public function media(BotMessage $message): StreamedResponse
    {
        $this->authorize('view', $message->conversation);

        abort_if(blank($message->media_path), 404);
        abort_unless(Storage::disk('local')->exists($message->media_path), 404);

        return Storage::disk('local')->response(
            $message->media_path,
            basename($message->media_path),
            ['Content-Type' => MediaService::mimeFor($message->media_path, $message->media_mime)],
        );
    }
}

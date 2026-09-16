<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bot\BotChannel;
use App\Models\Bot\BotRecipient;
use App\Models\ComplaintCategory;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Siapa yang dikabari saat pengaduan baru masuk, dan untuk kategori apa.
 *
 * Tabelnya, modelnya, dan OfficerNotifier yang memakainya sudah ada sejak
 * awal — yang belum ada hanyalah layar ini, sehingga tidak seorang pun pernah
 * dapat mengisinya. Akibatnya setiap pengaduan berhenti di panel admin dan
 * menunggu seseorang kebetulan membukanya.
 *
 * Penugasan dibuat per kategori, bukan satu daftar untuk semua: orang yang
 * mengurus irigasi tidak perlu dibanjiri pengaduan jalan berlubang, dan
 * pengaduan yang dikirim ke semua orang adalah pengaduan yang merasa bukan
 * tanggung jawab siapa-siapa.
 */
class BotRecipientController extends Controller
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function index(): View
    {
        $this->authorize('viewAny', BotRecipient::class);

        return view('admin.bot.recipients.index', [
            'recipients' => BotRecipient::with('categories')->orderBy('name')->get(),
            // Kategori yang belum dipegang siapa pun: pengaduan yang masuk ke
            // sana tidak akan memberi kabar kepada siapa pun, dan itu tidak
            // terlihat dari daftar petugas.
            'uncovered' => ComplaintCategory::where('is_active', true)
                ->whereDoesntHave('recipients', fn ($q) => $q->where('is_active', true))
                ->orderBy('sort_order')
                ->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', BotRecipient::class);

        return $this->form(new BotRecipient(['is_active' => true]));
    }

    public function edit(BotRecipient $recipient): View
    {
        $this->authorize('update', $recipient);

        return $this->form($recipient);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', BotRecipient::class);

        [$attributes, $categories] = $this->validated($request);

        $recipient = BotRecipient::create($attributes);
        $recipient->categories()->sync($categories);

        $this->audit->recordModel('create', 'bot_recipient', $recipient);

        return redirect()->route('admin.bot.recipients.index')->with('success', 'Petugas penerima ditambahkan.');
    }

    public function update(Request $request, BotRecipient $recipient): RedirectResponse
    {
        $this->authorize('update', $recipient);

        [$attributes, $categories] = $this->validated($request);
        $original = $recipient->getOriginal();

        $recipient->update($attributes);
        $recipient->categories()->sync($categories);

        $this->audit->recordModel('update', 'bot_recipient', $recipient, $original);

        return redirect()->route('admin.bot.recipients.index')->with('success', 'Petugas penerima diperbarui.');
    }

    public function destroy(BotRecipient $recipient): RedirectResponse
    {
        $this->authorize('delete', $recipient);

        $recipient->categories()->detach();
        $recipient->delete();

        $this->audit->record('delete', 'bot_recipient', $recipient->getKey());

        return redirect()->route('admin.bot.recipients.index')->with('success', 'Petugas penerima dihapus.');
    }

    private function form(BotRecipient $recipient): View
    {
        return view('admin.bot.recipients.form', [
            'recipient' => $recipient,
            'channels' => BotChannel::KEYS,
            'categories' => ComplaintCategory::where('is_active', true)->orderBy('sort_order')->get(),
            'selected' => old('categories', $recipient->exists
                ? $recipient->categories->pluck('id')->all()
                : []),
        ]);
    }

    /**
     * Kolom tabel dan daftar kategori dipisahkan.
     *
     * `categories` adalah relasi, bukan kolom: menyerahkannya ke create()
     * bersama yang lain membuat Eloquent menolak seluruh penyimpanan.
     *
     * @return array{0: array<string, mixed>, 1: array<int, int>}
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'channel' => ['required', 'string', Rule::in(array_keys(BotChannel::KEYS))],
            // Nomor WhatsApp dalam format internasional tanpa tanda baca
            // (6281…), atau chat id Telegram — angka, boleh negatif untuk grup.
            'destination' => ['required', 'string', 'max:64', 'regex:/^-?[0-9]+$/'],
            'is_active' => ['nullable', 'boolean'],
            'can_command' => ['nullable', 'boolean'],
            'categories' => ['array'],
            'categories.*' => ['integer', Rule::exists('complaint_categories', 'id')],
        ], [
            'destination.regex' => 'Isi angka saja: nomor WhatsApp format 62… tanpa tanda baca, atau chat id Telegram.',
        ]);

        $categories = array_map('intval', $data['categories'] ?? []);
        unset($data['categories']);

        return [
            $data + [
                'is_active' => $request->boolean('is_active'),
                'can_command' => $request->boolean('can_command'),
            ],
            $categories,
        ];
    }
}

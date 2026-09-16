<?php

namespace App\Services\Complaints;

use App\Models\Complaint;
use App\Models\ComplaintCategory;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Angka-angka pada layar pengaduan, untuk satu rentang waktu.
 *
 * Dipisah dari controller karena pertanyaannya bukan pertanyaan tampilan:
 * "jenis apa yang paling sering diadukan", "berapa yang benar-benar dijawab",
 * "berapa yang ditolak" — semuanya keputusan tentang cara menghitung, dan
 * masing-masing punya jebakannya sendiri.
 */
class ComplaintStatistics
{
    /** Pilihan periode pada layar, beserta panjangnya dalam hari. */
    public const PERIODS = [
        '7' => '7 hari terakhir',
        '30' => '30 hari terakhir',
        '90' => '90 hari terakhir',
        '365' => '1 tahun terakhir',
        'all' => 'Sejak awal',
    ];

    public function __construct(private readonly \App\Services\Bot\QuestionDigest $questions)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function between(?CarbonInterface $from, ?CarbonInterface $to): array
    {
        $byStatus = $this->byStatus($from, $to);
        $byCategory = $this->byCategory($from, $to);

        // Jenis yang tidak pernah diadukan sekali pun dalam periode ini tetap
        // ikut dihitung sebagai nol. Tanpa itu "paling sedikit" akan menunjuk
        // jenis dengan satu laporan, sementara jenis yang benar-benar sepi
        // hilang dari daftar — dan sepi adalah jawaban yang berguna.
        $ranked = $byCategory->sortByDesc('value')->values();

        return [
            'total' => (int) $byStatus->sum(),
            'byStatus' => $byStatus,
            'byCategory' => $ranked,
            'busiest' => $ranked->first(),
            'quietest' => $ranked->last(),
            'answered' => $this->answered($from, $to),
            'questions' => $this->questions->topics(
                \App\Models\BotQuestionTopic::NEW, 1, $from, $to,
            )->take(5),
        ];
    }

    /** @return Collection<string, int> */
    private function byStatus(?CarbonInterface $from, ?CarbonInterface $to): Collection
    {
        $rows = Complaint::query()
            ->when($from, fn ($query) => $query->where('created_at', '>=', $from))
            ->when($to, fn ($query) => $query->where('created_at', '<=', $to))
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        // Setiap status disebut meski nol, supaya kartunya tidak hilang dari
        // layar pada periode yang sepi.
        return collect(Complaint::STATUSES)
            ->map(fn (string $label, string $key) => (int) ($rows[$key] ?? 0));
    }

    /** @return Collection<int, array{label: string, value: int}> */
    private function byCategory(?CarbonInterface $from, ?CarbonInterface $to): Collection
    {
        $counts = Complaint::query()
            ->when($from, fn ($query) => $query->where('created_at', '>=', $from))
            ->when($to, fn ($query) => $query->where('created_at', '<=', $to))
            ->selectRaw('complaint_category_id, COUNT(*) as total')
            ->groupBy('complaint_category_id')
            ->pluck('total', 'complaint_category_id');

        return ComplaintCategory::where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (ComplaintCategory $category) => [
                'label' => $category->name,
                'value' => (int) ($counts[$category->getKey()] ?? 0),
            ]);
    }

    /**
     * Berapa pengaduan yang benar-benar dibalas kepada pelapornya.
     *
     * Dihitung dari riwayat yang statusnya TIDAK berubah: itulah bentuk yang
     * ditulis ReporterReplier, baik dari panel maupun dari `/jawab`. Perubahan
     * status juga menghasilkan baris riwayat, dan menghitungnya sebagai
     * jawaban akan membuat setiap pengaduan yang ditutup diam-diam tampak
     * sudah dijawab — angka yang menyenangkan dan tidak benar.
     *
     * Dihitung per pengaduan, bukan per balasan: tiga balasan pada satu
     * laporan tetap satu orang yang mendapat kabar.
     */
    private function answered(?CarbonInterface $from, ?CarbonInterface $to): int
    {
        return Complaint::query()
            ->when($from, fn ($query) => $query->where('created_at', '>=', $from))
            ->when($to, fn ($query) => $query->where('created_at', '<=', $to))
            ->whereExists(fn ($query) => $query
                ->select(DB::raw(1))
                ->from('complaint_updates')
                ->whereColumn('complaint_updates.complaint_id', 'complaints.id')
                ->whereColumn('complaint_updates.from_status', 'complaint_updates.to_status')
                ->whereNotNull('complaint_updates.note'))
            ->count();
    }
}

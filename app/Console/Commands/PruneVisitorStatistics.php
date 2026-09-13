<?php

namespace App\Console\Commands;

use App\Models\PageView;
use App\Services\Analytics\VisitorStatistics;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class PruneVisitorStatistics extends Command
{
    protected $signature = 'visitors:prune {--days= : Override the configured retention}';

    protected $description = 'Menghapus catatan kunjungan yang lebih tua dari masa simpan';

    public function handle(VisitorStatistics $statistics): int
    {
        $days = (int) ($this->option('days') ?: config('analytics.retention_days', 365));

        if ($days < 1) {
            $this->error('Masa simpan harus minimal 1 hari.');

            return self::FAILURE;
        }

        $cutoff = Carbon::today()->subDays($days);

        // Deleted in chunks so a long-neglected table does not lock for minutes
        // or exhaust memory in one statement.
        $deleted = 0;

        do {
            $batch = PageView::where('viewed_on', '<', $cutoff->copy()->startOfDay())->limit(5000)->delete();
            $deleted += $batch;
        } while ($batch > 0);

        $statistics->forget();

        $this->info("Menghapus {$deleted} catatan kunjungan sebelum {$cutoff->toDateString()}.");

        return self::SUCCESS;
    }
}


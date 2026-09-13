<?php

namespace App\Console\Commands;

use App\Models\SocialPost;
use App\Services\Social\SocialSyncService;
use Illuminate\Console\Command;

class SyncSocialPosts extends Command
{
    protected $signature = 'social:sync
        {--post= : Sinkronkan satu unggahan berdasarkan id}
        {--all : Termasuk unggahan yang sinkronisasinya dimatikan}';

    protected $description = 'Membaca ulang jumlah suka, komentar, dan nama akun dari platform asalnya';

    public function handle(SocialSyncService $sync): int
    {
        $query = SocialPost::query();

        if ($id = $this->option('post')) {
            $query->whereKey($id);
        } elseif (! $this->option('all')) {
            $query->syncable();
        }

        $posts = $query->get();

        if ($posts->isEmpty()) {
            $this->info('Tidak ada unggahan untuk disinkronkan.');

            return self::SUCCESS;
        }

        $tally = $sync->syncMany($posts);

        $this->table(
            ['Unggahan', 'Status', 'Keterangan'],
            $posts->map(fn (SocialPost $post) => [
                $post->platformLabel().' #'.$post->getKey(),
                $post->sync_status,
                mb_strimwidth((string) $post->sync_message, 0, 70, '…'),
            ]),
        );

        $this->line(sprintf(
            '%d tersinkron, %d gagal, %d tidak didukung.',
            $tally['ok'], $tally['failed'], $tally['unsupported'],
        ));

        // Failures are reported but do not fail the run: one unreachable
        // platform must not mark a scheduled task as broken.
        return self::SUCCESS;
    }
}

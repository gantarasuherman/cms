<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Catatan bahwa sebuah pengaduan pernah diteruskan, dan ke mana.
 *
 * Nama dan nomor tujuan disalin ke baris ini, tidak hanya dirujuk: sebuah
 * instansi dapat dihapus dari daftar atau berganti nomor, sementara riwayat
 * harus tetap dapat menjawab "waktu itu dikirim ke mana".
 */
class ComplaintDisposition extends Model
{
    protected $fillable = [
        'complaint_id', 'disposition_target_id', 'target_name', 'target_phone',
        'note', 'source', 'source_actor',
    ];

    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(DispositionTarget::class, 'disposition_target_id');
    }
}

<?php

namespace App\Policies;

use App\Models\User;

/**
 * Tujuan penerusan ikut modul `complaint`.
 *
 * Berbeda dengan jenis pengaduan — yang menentukan apa yang ditanyakan bot dan
 * karena itu ikut `chatbot` — daftar ini murni pekerjaan antrean: operator yang
 * menggarap pengaduan sehari-hari adalah orang yang tahu instansi mana yang
 * menangani apa.
 *
 * Modul `complaint` tidak punya `create`; tujuan penerusan jelas perlu dibuat,
 * jadi kemampuan itu dipinjam dari `update` — orang yang boleh mengubah
 * pengaduan boleh menyusun daftar tujuannya.
 */
class DispositionTargetPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'complaint';
    }

    public function create(User $user): bool
    {
        return $user->can('complaint.update');
    }
}

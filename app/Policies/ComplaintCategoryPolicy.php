<?php

namespace App\Policies;

/**
 * Kategori pengaduan ikut modul `chatbot`, bukan `complaint`.
 *
 * Dua sebabnya. Modul `complaint` sengaja tidak punya `create` — pengaduan
 * tidak pernah dibuat dari panel — sedangkan kategori jelas perlu dibuat.
 * Dan isinya memang keputusan konfigurasi: kategori menentukan apa yang
 * ditanyakan bot sebelum ia mau mencatat laporan. Operator yang menggarap
 * antrean boleh melihatnya, tetapi mengubah syarat bukti bukan pekerjaan
 * antrean.
 */
class ComplaintCategoryPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'chatbot';
    }
}

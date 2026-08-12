<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Bidang;

/**
 * Menjaga kolom `status` tetap sesuai tanggal tahap dan kendala aktif.
 *
 * Status bukan isian operator (docs/spec.md bagian 3), tetapi tetap disimpan
 * sebagai kolom supaya dapat disaring dan diagregasi di SQL. Observer inilah
 * satu-satunya penulisnya.
 */
class BidangObserver
{
    public function saving(Bidang $bidang): void
    {
        $bidang->setAttribute('status', $bidang->statusHitung);
    }
}

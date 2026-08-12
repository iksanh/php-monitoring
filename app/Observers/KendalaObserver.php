<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Kendala;

/**
 * Status bidang ikut berubah begitu kendalanya dicatat, ditutup, atau dihapus.
 *
 * Perhitungannya sendiri ada di BidangObserver; di sini bidang induk cukup
 * disimpan ulang agar observer itu jalan.
 */
class KendalaObserver
{
    public function saved(Kendala $kendala): void
    {
        $this->segarkanBidang($kendala);
    }

    public function deleted(Kendala $kendala): void
    {
        $this->segarkanBidang($kendala);
    }

    private function segarkanBidang(Kendala $kendala): void
    {
        $bidang = $kendala->bidang()->first();

        if ($bidang === null) {
            return;
        }

        // Relasi yang mungkin sudah termuat sebelum perubahan ini sengaja
        // dibuang: statusHitung harus membaca keadaan kendala terbaru.
        $bidang->unsetRelation('kendala')->unsetRelation('kendalaAktif');

        $bidang->save();
    }
}

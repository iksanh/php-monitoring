<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Bidang;
use App\Support\Tahapan;

/**
 * Menjaga kolom `status` tetap sesuai tanggal tahap, penanda diproses, dan
 * kendala aktif.
 *
 * Status bukan isian operator (docs/spec.md bagian 3), tetapi tetap disimpan
 * sebagai kolom supaya dapat disaring dan diagregasi di SQL. Observer inilah
 * satu-satunya penulisnya.
 */
class BidangObserver
{
    public function saving(Bidang $bidang): void
    {
        $this->tandaiDiproses($bidang);

        $bidang->setAttribute('status', $bidang->statusHitung);
    }

    /**
     * Menentukan sendiri kapan sebuah bidang masuk pantauan.
     *
     * Dua keadaan yang ditangani:
     *
     * - Bidang baru yang dibuat lewat aplikasi — form maupun impor Excel —
     *   memang sedang dikerjakan, jadi langsung dipantau. Yang menaruh bidang
     *   di antrean hanya `impor:daftar-aset`, dan perintah itu mengisi
     *   `tgl_diproses` dengan null secara tegas.
     * - Bidang antrean yang tanggal tahapnya baru diisi operator. Mengisi
     *   tanggal tahap berarti pekerjaannya sudah jalan, jadi penandanya
     *   menyusul sendiri tanpa perlu menekan tombol proses lebih dulu.
     */
    private function tandaiDiproses(Bidang $bidang): void
    {
        if (! $bidang->exists) {
            if (! array_key_exists('tgl_diproses', $bidang->getAttributes())) {
                $bidang->setAttribute('tgl_diproses', today());
            }

            return;
        }

        if ($bidang->tgl_diproses !== null) {
            return;
        }

        foreach (Tahapan::kolomTanggal() as $kolom) {
            if ($bidang->isDirty($kolom) && $bidang->getAttribute($kolom) !== null) {
                $bidang->setAttribute('tgl_diproses', today());

                return;
            }
        }
    }
}

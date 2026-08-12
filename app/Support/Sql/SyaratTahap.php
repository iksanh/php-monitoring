<?php

declare(strict_types=1);

namespace App\Support\Sql;

use App\Enums\StatusTahap;
use App\Support\Tahap;
use App\Support\Tahapan;
use InvalidArgumentException;

/**
 * Perakit syarat SQL untuk tahap turunan.
 *
 * Tahap aktif dan tahap berikut tidak pernah disimpan sebagai kolom — keduanya
 * diturunkan dari kedelapan kolom tanggal beserta status tahap kondisional.
 * Kelas ini menerjemahkan turunan itu ke SQL supaya dashboard bisa mengagregasi
 * dan daftar bidang bisa menyaring tanpa menarik seluruh tabel ke PHP.
 *
 * Kebenarannya dijaga test yang mengadu hasil SQL dengan accessor pada model.
 */
final class SyaratTahap
{
    /**
     * Bidang sedang menunggu tahap ini, yakni `tahapBerikut` bidang tersebut
     * adalah tahap ini:
     *
     *  a. tahap ini berlaku untuk bidang tersebut,
     *  b. tanggalnya belum terisi,
     *  c. tidak ada tahap berlaku sesudahnya yang sudah terisi, dan
     *  d. tahap berlaku tepat sebelumnya sudah terisi — atau tidak ada tahap
     *     berlaku sebelum ini sama sekali.
     *
     * Syarat (d) yang membuat bidang dengan tahap terlewat tetap terhitung di
     * tahap yang benar.
     */
    public function menunggu(Tahap $tahap): string
    {
        $tahapan = Tahapan::semua();
        $indeks = $tahap->urutan - 1;

        $syarat = [
            $this->berlaku($tahap),
            $this->kolomAman($tahap->kolom).' is null',
            ...$this->sesudahnyaKosong($tahapan, $indeks),
            $this->pendahuluTerisi($tahapan, $indeks),
        ];

        return '('.implode(' and ', $syarat).')';
    }

    /**
     * Tahap aktif bidang ini adalah tahap tersebut: berlaku, tanggalnya
     * terisi, dan tidak ada tahap berlaku sesudahnya yang terisi.
     */
    public function aktif(Tahap $tahap): string
    {
        $tahapan = Tahapan::semua();

        $syarat = [
            $this->berlaku($tahap),
            $this->kolomAman($tahap->kolom).' is not null',
            ...$this->sesudahnyaKosong($tahapan, $tahap->urutan - 1),
        ];

        return '('.implode(' and ', $syarat).')';
    }

    /**
     * Belum ada satu pun tahap berlaku yang terisi.
     */
    public function belumMulai(): string
    {
        return '('.implode(' and ', $this->sesudahnyaKosong(Tahapan::semua(), -1)).')';
    }

    /**
     * Gabungan syarat menunggu untuk sekumpulan tahap, mis. seluruh tahap yang
     * penanggung jawabnya sama.
     *
     * @param  list<Tahap>  $tahapan
     */
    public function menungguSalahSatu(array $tahapan): string
    {
        if ($tahapan === []) {
            return '1 = 0';
        }

        return '('.implode(' or ', array_map(fn (Tahap $tahap): string => $this->menunggu($tahap), $tahapan)).')';
    }

    public function ekspresi(string $sql): EkspresiAgregasi
    {
        return new EkspresiAgregasi($sql);
    }

    /**
     * Nama kolom berasal dari config, bukan dari pengguna, tetapi query ini
     * dirakit sebagai teks mentah — jadi tetap diperiksa.
     */
    public function kolomAman(string $kolom): string
    {
        if (preg_match('/^[a-z_][a-z0-9_]*$/i', $kolom) !== 1) {
            throw new InvalidArgumentException("Nama kolom [{$kolom}] tidak sah untuk query.");
        }

        return $kolom;
    }

    /**
     * Nilai enum yang ditulis langsung ke dalam SQL. Seluruhnya konstanta di
     * kode, bukan masukan pengguna, tetapi tetap dibatasi bentuknya.
     */
    public function nilaiAman(string $nilai): string
    {
        if (preg_match('/^[a-z0-9_]+$/i', $nilai) !== 1) {
            throw new InvalidArgumentException("Nilai [{$nilai}] tidak sah untuk query.");
        }

        return "'".$nilai."'";
    }

    /**
     * Seluruh tahap berlaku sesudah indeks tertentu belum terisi.
     *
     * @param  list<Tahap>  $tahapan
     * @return list<string>
     */
    private function sesudahnyaKosong(array $tahapan, int $indeks): array
    {
        $syarat = [];

        foreach (array_slice($tahapan, $indeks + 1) as $sesudah) {
            $syarat[] = $sesudah->kolomStatus === null
                ? $this->kolomAman($sesudah->kolom).' is null'
                : '('.$this->tidakBerlaku($sesudah).' or '.$this->kolomAman($sesudah->kolom).' is null)';
        }

        return $syarat;
    }

    /**
     * @param  list<Tahap>  $tahapan
     */
    private function pendahuluTerisi(array $tahapan, int $indeks): string
    {
        if ($indeks === 0) {
            return '1 = 1';
        }

        $pilihan = [];
        $diantara = [];

        for ($j = $indeks - 1; $j >= 0; $j--) {
            $sebelum = $tahapan[$j];

            $pilihan[] = '('.implode(' and ', array_merge(
                [$this->berlaku($sebelum), $this->kolomAman($sebelum->kolom).' is not null'],
                $diantara,
            )).')';

            // Tahap wajib selalu berlaku, jadi pencarian pendahulu berhenti di
            // sini: tahap sebelum ini tidak mungkin menjadi tahap aktif.
            if ($sebelum->kolomStatus === null) {
                return '('.implode(' or ', $pilihan).')';
            }

            $diantara[] = $this->tidakBerlaku($sebelum);
        }

        // Seluruh tahap sebelumnya kondisional; bila semuanya tidak berlaku,
        // tahap ini adalah tahap berlaku pertama.
        $pilihan[] = '('.implode(' and ', $diantara).')';

        return '('.implode(' or ', $pilihan).')';
    }

    private function berlaku(Tahap $tahap): string
    {
        if ($tahap->kolomStatus === null) {
            return '1 = 1';
        }

        return $this->kolomAman($tahap->kolomStatus).' = '.$this->nilaiAman(StatusTahap::Berlaku->value);
    }

    private function tidakBerlaku(Tahap $tahap): string
    {
        if ($tahap->kolomStatus === null) {
            return '1 = 0';
        }

        return $this->kolomAman($tahap->kolomStatus).' = '.$this->nilaiAman(StatusTahap::TidakBerlaku->value);
    }
}

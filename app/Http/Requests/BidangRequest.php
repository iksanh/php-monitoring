<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Bidang;
use App\Support\Tahapan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BidangRequest extends FormRequest
{
    /**
     * Tidak ada validasi urutan tahap. Operator harus bebas mengisi tanggal
     * mana pun, termasuk melewati tahap — lihat docs/spec.md bagian 1.
     *
     * Tidak ada aturan `status`: nilainya turunan, dihitung BidangObserver.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $bidang = $this->route('bidang');

        $aturan = [
            'nomor_urut' => [
                'required', 'string', 'max:50',
                Rule::unique('bidang', 'nomor_urut')
                    ->ignore($bidang instanceof Bidang ? $bidang->id : null)
                    ->withoutTrashed(),
            ],
            'nama_aset' => ['required', 'string', 'max:255'],
            'instansi_id' => ['required', Rule::exists('instansi', 'id')],
            'penggunaan' => ['nullable', 'string', 'max:255'],
            'desa' => ['required', 'string', 'max:255'],
            'kecamatan' => ['required', 'string', 'max:255'],
            'luas_m2' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'nomor_berkas_kkp' => ['nullable', 'string', 'max:255'],
            'tahun_target' => ['required', 'integer', 'min:2000', 'max:2100'],
            'keterangan' => ['nullable', 'string', 'max:5000'],
        ];

        foreach (Tahapan::semua() as $tahap) {
            $aturan[$tahap->kolom] = ['nullable', 'date'];
        }

        return $aturan;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $nama = [
            'nomor_urut' => 'nomor urut',
            'nama_aset' => 'nama aset',
            'instansi_id' => 'instansi pemilik aset',
            'luas_m2' => 'luas',
            'nomor_berkas_kkp' => 'nomor berkas KKP',
            'tahun_target' => 'tahun target',
        ];

        foreach (Tahapan::semua() as $tahap) {
            $nama[$tahap->kolom] = 'tanggal '.mb_strtolower($tahap->label);
        }

        return $nama;
    }
}

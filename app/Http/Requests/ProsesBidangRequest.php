<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Aksi massal atas daftar bidang: menandai bidang terpilih sedang diproses,
 * atau mengembalikannya ke antrean daftar aset.
 *
 * Dua cara memilih. Kotak centang mengirim daftar id; centang "seluruh hasil
 * penyaringan" tidak mengirim id sama sekali dan membiarkan penyaring pada
 * query string yang menentukan barisnya — supaya ribuan bidang tidak perlu
 * dikirim satu per satu sebagai isian form.
 */
class ProsesBidangRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'bidang' => ['array'],
            'bidang.*' => ['integer', 'min:1'],
            'semua_hasil' => ['nullable', 'boolean'],
            'tahun_target' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->semuaHasil() && $this->idTerpilih() === []) {
                    $validator->errors()->add(
                        'bidang',
                        'Belum ada bidang yang dicentang. Pilih dulu bidangnya, atau centang seluruh hasil penyaringan.'
                    );
                }
            },
        ];
    }

    public function semuaHasil(): bool
    {
        return $this->boolean('semua_hasil');
    }

    /**
     * @return list<int>
     */
    public function idTerpilih(): array
    {
        /** @var array<int|string, mixed> $terpilih */
        $terpilih = $this->input('bidang', []);

        return array_values(array_unique(array_map(
            static fn (mixed $id): int => (int) $id,
            array_filter($terpilih, static fn (mixed $id): bool => is_numeric($id)),
        )));
    }

    public function tahunTarget(): int
    {
        $tahun = $this->integer('tahun_target');

        return $tahun >= 2000 && $tahun <= 2100 ? $tahun : (int) now()->format('Y');
    }
}

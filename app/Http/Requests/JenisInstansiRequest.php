<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\JenisInstansi;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class JenisInstansiRequest extends FormRequest
{
    /**
     * Kode tidak ikut divalidasi karena tidak diisi pengguna — dibuat sekali
     * dari nama lalu dikunci.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $jenis = $this->route('jenis_instansi');

        return [
            'nama' => [
                'required', 'string', 'max:255',
                Rule::unique('jenis_instansi', 'nama')
                    ->ignore($jenis instanceof JenisInstansi ? $jenis->id : null),
            ],
            'aktif' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['nama' => 'nama jenis'];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['aktif' => $this->boolean('aktif')]);
    }
}

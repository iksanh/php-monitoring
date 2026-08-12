<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Instansi;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InstansiRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $instansi = $this->route('instansi');

        return [
            'nama' => [
                'required', 'string', 'max:255',
                Rule::unique('instansi', 'nama')
                    ->ignore($instansi instanceof Instansi ? $instansi->id : null),
            ],
            'jenis_instansi_id' => ['required', Rule::exists('jenis_instansi', 'id')],
            'aktif' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['aktif' => $this->boolean('aktif')]);
    }
}

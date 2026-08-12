<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\KategoriKendala;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class KendalaRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'kategori' => ['required', new Enum(KategoriKendala::class)],
            'uraian' => ['required', 'string', 'max:2000'],
            'tanggal_catat' => ['required', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_catat'],
            'dicatat_oleh' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'kategori' => 'kategori kendala',
            'tanggal_catat' => 'tanggal catat',
            'tanggal_selesai' => 'tanggal selesai',
            'dicatat_oleh' => 'dicatat oleh',
        ];
    }
}

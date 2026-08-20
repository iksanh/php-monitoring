<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImporBidangRequest extends FormRequest
{
    /**
     * Wewenangnya dijaga Gate di controller, sejalan dengan controller bidang
     * lainnya.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // 5 MB sudah jauh di atas berkas seribu baris; batas ini menjaga
            // shared hosting dari unggahan yang tidak masuk akal.
            'berkas' => ['required', 'file', 'max:5120', 'extensions:xlsx,xls,csv'],
            'perbarui' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'berkas' => 'berkas Excel',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'berkas.required' => 'Pilih dulu berkas yang akan diunggah.',
            'berkas.extensions' => 'Berkas harus berformat .xlsx, .xls, atau .csv.',
            'berkas.max' => 'Ukuran berkas melebihi 5 MB. Pecah menjadi beberapa berkas.',
        ];
    }

    public function bolehPerbarui(): bool
    {
        return $this->boolean('perbarui');
    }
}

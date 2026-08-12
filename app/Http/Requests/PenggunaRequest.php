<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\Peran;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class PenggunaRequest extends FormRequest
{
    use PasswordValidationRules;

    // Aturan nama dan surel dipakai bersama halaman ubah profil.
    use ProfileValidationRules;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $pengguna = $this->route('pengguna');
        $adalahPembaruan = $pengguna instanceof User;

        return [
            ...$this->profileRules($adalahPembaruan ? $pengguna->id : null),
            'role' => ['required', new Enum(Peran::class)],
            'instansi_id' => ['nullable', Rule::exists('instansi', 'id')],
            // Saat mengubah pengguna, kata sandi hanya diganti bila diisi.
            'password' => $adalahPembaruan
                ? ['nullable', 'string', 'confirmed']
                : $this->passwordRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nama',
            'role' => 'peran',
            'instansi_id' => 'instansi',
            'password' => 'kata sandi',
        ];
    }
}

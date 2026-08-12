<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Peran pengguna (kolom `users.role`).
 *
 * `viewer` bersifat read-only atas SELURUH data lintas instansi — tidak
 * dibatasi ke instansinya sendiri. Keterbukaan lintas instansi adalah tujuan
 * aplikasi ini. Penegakannya lewat Gate/Policy pada TAHAP B.
 */
enum Peran: string
{
    case Admin = 'admin';
    case Operator = 'operator';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Operator => 'Operator',
            self::Viewer => 'Pemantau',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function pilihan(): array
    {
        $pilihan = [];

        foreach (self::cases() as $case) {
            $pilihan[$case->value] = $case->label();
        }

        return $pilihan;
    }
}

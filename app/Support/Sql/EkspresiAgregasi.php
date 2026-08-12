<?php

declare(strict_types=1);

namespace App\Support\Sql;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Grammar;

/**
 * Potongan SQL yang dirakit di kode, bukan ditulis sebagai teks tetap.
 *
 * `Illuminate\Database\Query\Expression` bawaan Laravel sengaja dibatasi ke
 * `literal-string` supaya string dari luar tidak bisa menyelinap ke dalam
 * query. Sebaran tahap dashboard harus dirakit dari `config('tahapan')`, jadi
 * ia tidak mungkin literal — dan justru karena itu batasnya dipindah ke sini:
 *
 * Yang boleh masuk hanya SQL yang seluruh nama kolom dan nilainya sudah
 * diperiksa bentuknya oleh pemanggil (lihat DashboardService::kolomAman() dan
 * nilaiAman()). Tidak ada masukan pengguna yang pernah sampai ke kelas ini.
 */
final readonly class EkspresiAgregasi implements Expression
{
    public function __construct(private string $sql) {}

    public function getValue(Grammar $grammar): string
    {
        return $this->sql;
    }
}

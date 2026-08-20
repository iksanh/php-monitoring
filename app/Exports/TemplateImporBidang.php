<?php

declare(strict_types=1);

namespace App\Exports;

use App\Exports\Template\LembarBidang;
use App\Exports\Template\LembarPetunjuk;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Template berkas impor bidang: lembar isian di depan, lembar petunjuk di
 * belakang, supaya berkas terbuka langsung pada tempat mengetik.
 */
class TemplateImporBidang implements WithMultipleSheets
{
    /**
     * @return list<object>
     */
    public function sheets(): array
    {
        return [
            new LembarBidang,
            new LembarPetunjuk,
        ];
    }
}

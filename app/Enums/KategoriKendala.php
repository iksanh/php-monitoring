<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Kategori hambatan di luar alur normal.
 *
 * Kekurangan berkas bisa muncul di tahap mana pun, jadi dicatat sebagai
 * kendala — bukan sebagai tahap tersendiri. Lihat docs/spec.md bagian 3.
 *
 * Berkas yang belum ber-PKKPR BUKAN kendala: kondisi itu sudah terbaca dari
 * `tgl_permohonan` terisi sementara `tgl_pkkpr` kosong.
 */
enum KategoriKendala: string
{
    case BerkasKurang = 'berkas_kurang';
    case MenungguPemohon = 'menunggu_pemohon';
    case Sengketa = 'sengketa';
    case OverlapBidang = 'overlap_bidang';
    case KawasanHutan = 'kawasan_hutan';
    case Lainnya = 'lainnya';

    public function label(): string
    {
        return match ($this) {
            self::BerkasKurang => 'Berkas kurang',
            self::MenungguPemohon => 'Menunggu pemohon',
            self::Sengketa => 'Sengketa',
            self::OverlapBidang => 'Overlap bidang',
            self::KawasanHutan => 'Kawasan hutan',
            self::Lainnya => 'Lainnya',
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

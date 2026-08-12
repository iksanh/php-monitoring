<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PenanggungJawab;
use App\Enums\StatusBidang;
use App\Enums\StatusTahap;
use App\Support\Tahap;
use App\Support\Tahapan;
use Carbon\CarbonInterface;
use Database\Factories\BidangFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $nomor_urut
 * @property string $nama_aset
 * @property int $instansi_id
 * @property string|null $penggunaan
 * @property string $desa
 * @property string $kecamatan
 * @property string|null $luas_m2
 * @property string|null $nomor_berkas_kkp
 * @property int $tahun_target
 * @property string|null $keterangan
 * @property StatusBidang $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Instansi $instansi
 * @property-read Collection<int, Kendala> $kendala
 * @property-read Tahap|null $tahapAktif
 * @property-read Tahap|null $tahapBerikut
 * @property-read PenanggungJawab|null $penanggungJawab
 * @property-read int|null $umurHari
 * @property-read int $persenProgres
 */
class Bidang extends Model
{
    /** @use HasFactory<BidangFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Kolom penanda terbitnya produk, dipakai sebagai batas akhir `umurHari`.
     *
     * Ini satu-satunya tempat kolom tahap disebut langsung, sebab "sampai
     * sertipikat terbit" adalah definisi umur berkas menurut spec — bukan
     * posisi tahap. Label, unit, dan urutannya tetap dibaca dari config.
     */
    public const KOLOM_TERBIT = 'tgl_sertipikat';

    protected $table = 'bidang';

    /**
     * Kolom tanggal tahap dan kolom statusnya ditambahkan secara dinamis
     * dari config('tahapan') — lihat getFillable().
     *
     * @var list<string>
     */
    protected $fillable = [
        'nomor_urut',
        'nama_aset',
        'instansi_id',
        'penggunaan',
        'desa',
        'kecamatan',
        'luas_m2',
        'nomor_berkas_kkp',
        'tahun_target',
        'keterangan',
        'status',
    ];

    /**
     * @return list<string>
     */
    public function getFillable(): array
    {
        return array_values(array_unique(array_merge(
            $this->fillable,
            Tahapan::kolomTanggal(),
            Tahapan::kolomStatus(),
        )));
    }

    /**
     * @return BelongsTo<Instansi, $this>
     */
    public function instansi(): BelongsTo
    {
        return $this->belongsTo(Instansi::class);
    }

    /**
     * @return HasMany<Kendala, $this>
     */
    public function kendala(): HasMany
    {
        return $this->hasMany(Kendala::class);
    }

    /**
     * Tahap yang berlaku untuk bidang ini: seluruh tahap dari config, dikurangi
     * tahap kondisional yang dinyatakan `tidak_berlaku`.
     *
     * @return list<Tahap>
     */
    public function tahapBerlaku(): array
    {
        return array_values(array_filter(
            Tahapan::semua(),
            fn (Tahap $tahap): bool => $this->tahapDipakai($tahap),
        ));
    }

    /**
     * Apakah tahap ini dihitung untuk bidang ini.
     */
    public function tahapDipakai(Tahap $tahap): bool
    {
        if ($tahap->kolomStatus === null) {
            return true;
        }

        $status = $this->getAttribute($tahap->kolomStatus);

        return ! $status instanceof StatusTahap || $status->berlaku();
    }

    /**
     * Tanggal terisi pada suatu tahap, null bila belum.
     */
    public function tanggalTahap(Tahap $tahap): ?CarbonInterface
    {
        $tanggal = $this->getAttribute($tahap->kolom);

        return $tanggal instanceof CarbonInterface ? $tanggal : null;
    }

    /**
     * Tahap berlaku terakhir yang tanggalnya sudah terisi.
     *
     * Sengaja tidak menuntut urutan: operator bebas melewati tahap, jadi yang
     * diambil adalah tahap terisi dengan urutan tertinggi, bukan tahap terisi
     * terakhir yang berurutan.
     *
     * @return Attribute<Tahap|null, never>
     */
    protected function tahapAktif(): Attribute
    {
        return Attribute::make(get: function (): ?Tahap {
            $aktif = null;

            foreach ($this->tahapBerlaku() as $tahap) {
                if ($this->tanggalTahap($tahap) !== null) {
                    $aktif = $tahap;
                }
            }

            return $aktif;
        })->withoutObjectCaching();
    }

    /**
     * Tahap berlaku sesudah tahap aktif. Bila belum ada tanggal sama sekali,
     * yang berikutnya adalah tahap berlaku pertama. Null bila sudah tuntas.
     *
     * @return Attribute<Tahap|null, never>
     */
    protected function tahapBerikut(): Attribute
    {
        return Attribute::make(get: function (): ?Tahap {
            $berlaku = $this->tahapBerlaku();
            $aktif = $this->tahapAktif;

            if ($aktif === null) {
                return $berlaku[0] ?? null;
            }

            foreach ($berlaku as $index => $tahap) {
                if ($tahap->kolom === $aktif->kolom) {
                    return $berlaku[$index + 1] ?? null;
                }
            }

            return null;
        })->withoutObjectCaching();
    }

    /**
     * Pihak yang memegang bola saat ini, yaitu penanggung jawab tahap berikut.
     *
     * @return Attribute<PenanggungJawab|null, never>
     */
    protected function penanggungJawab(): Attribute
    {
        return Attribute::make(
            get: fn (): ?PenanggungJawab => $this->tahapBerikut?->penanggungJawab
        )->withoutObjectCaching();
    }

    /**
     * Umur berkas dalam hari: dari tahap pertama sampai sertipikat terbit,
     * atau sampai hari ini bila belum terbit. Null bila tahap pertama kosong.
     *
     * @return Attribute<int|null, never>
     */
    protected function umurHari(): Attribute
    {
        return Attribute::make(get: function (): ?int {
            $mulai = $this->tanggalTahap(Tahapan::pertama());

            if ($mulai === null) {
                return null;
            }

            $tahapTerbit = Tahapan::cari(self::KOLOM_TERBIT);
            $selesai = $tahapTerbit !== null ? $this->tanggalTahap($tahapTerbit) : null;

            return (int) $mulai->startOfDay()->diffInDays(
                ($selesai ?? now())->startOfDay()
            );
        });
    }

    /**
     * Persentase tahap berlaku yang tanggalnya sudah terisi.
     *
     * @return Attribute<int, never>
     */
    protected function persenProgres(): Attribute
    {
        return Attribute::make(get: function (): int {
            $berlaku = $this->tahapBerlaku();

            if ($berlaku === []) {
                return 0;
            }

            $terisi = 0;

            foreach ($berlaku as $tahap) {
                if ($this->tanggalTahap($tahap) !== null) {
                    $terisi++;
                }
            }

            return (int) round($terisi / count($berlaku) * 100);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        $casts = [
            'tahun_target' => 'integer',
            'luas_m2' => 'decimal:2',
            'status' => StatusBidang::class,
        ];

        foreach (Tahapan::semua() as $tahap) {
            $casts[$tahap->kolom] = 'date';

            if ($tahap->kolomStatus !== null) {
                $casts[$tahap->kolomStatus] = StatusTahap::class;
            }
        }

        return $casts;
    }
}

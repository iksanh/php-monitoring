<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PenanggungJawab;
use App\Enums\StatusBidang;
use App\Observers\BidangObserver;
use App\Support\Tahap;
use App\Support\Tahapan;
use Carbon\CarbonInterface;
use Database\Factories\BidangFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
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
 * @property string|null $pengguna_barang
 * @property string|null $penggunaan
 * @property string $desa
 * @property string|null $kecamatan
 * @property string|null $luas_m2
 * @property string|null $jenis_hak
 * @property string|null $alas_hak
 * @property Carbon|null $tgl_alas_hak
 * @property string|null $asal_usul
 * @property string|null $nomor_berkas_kkp
 * @property string|null $nomor_sertipikat
 * @property int $tahun_target
 * @property CarbonInterface|null $tgl_diproses
 * @property string|null $keterangan
 * @property StatusBidang $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Instansi $instansi
 * @property-read Collection<int, Kendala> $kendala
 * @property-read Collection<int, Kendala> $kendalaAktif
 * @property-read Tahap|null $tahapAktif
 * @property-read Tahap|null $tahapBerikut
 * @property-read PenanggungJawab|null $penanggungJawab
 * @property-read int|null $umurHari
 * @property-read int $persenProgres
 * @property-read StatusBidang $statusHitung
 * @property-read string $kondisiTahap
 * @property-read string $letak
 */
#[ObservedBy(BidangObserver::class)]
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

    /**
     * Kolom penanda aset sudah diserahkan, dipakai menghitung status.
     *
     * Alasannya sama dengan KOLOM_TERBIT: "sudah diserahkan" adalah definisi
     * status menurut docs/spec.md bagian 3, bukan posisi tahap.
     */
    public const KOLOM_SERAH_TERIMA = 'tgl_serah_terima';

    /**
     * Sebutan bidang yang seluruh tahapnya sudah terisi.
     */
    public const KONDISI_TUNTAS = 'Sudah Diserahkan';

    protected $table = 'bidang';

    /**
     * Kolom tanggal tahap ditambahkan secara dinamis dari config('tahapan') —
     * lihat getFillable(). `status` sengaja TIDAK fillable: nilainya turunan,
     * dihitung BidangObserver, bukan dikirim operator.
     *
     * @var list<string>
     */
    protected $fillable = [
        'nomor_urut',
        'nama_aset',
        'instansi_id',
        'pengguna_barang',
        'penggunaan',
        'desa',
        'kecamatan',
        'luas_m2',
        'jenis_hak',
        'alas_hak',
        'tgl_alas_hak',
        'asal_usul',
        'nomor_berkas_kkp',
        'nomor_sertipikat',
        'tahun_target',
        'tgl_diproses',
        'keterangan',
    ];

    /**
     * @return list<string>
     */
    public function getFillable(): array
    {
        return array_values(array_unique(array_merge(
            $this->fillable,
            Tahapan::kolomTanggal(),
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
     * @return HasMany<Kendala, $this>
     */
    public function kendalaAktif(): HasMany
    {
        return $this->kendala()->whereNull('tanggal_selesai');
    }

    /**
     * Tahap yang berlaku untuk bidang ini: seluruh tahap dari config.
     *
     * Sejak daftar tahapan disederhanakan, tidak ada lagi tahap kondisional —
     * metode ini disisakan sebagai satu pintu baca supaya pemanggilnya tidak
     * perlu berubah bila kelak ada pengecualian per bidang lagi.
     *
     * @return list<Tahap>
     */
    public function tahapBerlaku(): array
    {
        return Tahapan::semua();
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
     * Persentase tahap yang tanggalnya sudah terisi.
     *
     * @return Attribute<int, never>
     */
    protected function persenProgres(): Attribute
    {
        return Attribute::make(get: function (): int {
            $tahapan = $this->tahapBerlaku();

            if ($tahapan === []) {
                return 0;
            }

            $terisi = 0;

            foreach ($tahapan as $tahap) {
                if ($this->tanggalTahap($tahap) !== null) {
                    $terisi++;
                }
            }

            return (int) round($terisi / count($tahapan) * 100);
        });
    }

    /**
     * Status menurut tanggal tahap dan kendala aktif — lihat tabel di
     * docs/spec.md bagian 3. `terkendala` menang atas yang lain.
     *
     * Ini sumber nilai kolom `status`; operator tidak mengisinya sendiri.
     *
     * @return Attribute<StatusBidang, never>
     */
    protected function statusHitung(): Attribute
    {
        return Attribute::make(get: function (): StatusBidang {
            if ($this->adaKendalaAktif()) {
                return StatusBidang::Terkendala;
            }

            if ($this->getAttribute(self::KOLOM_SERAH_TERIMA) !== null) {
                return StatusBidang::Diserahkan;
            }

            if ($this->getAttribute(self::KOLOM_TERBIT) !== null) {
                return StatusBidang::Selesai;
            }

            // Bidang yang belum dipilih Kantor Pertanahan masih sebatas
            // daftar aset. Menyebutnya "dalam proses" membuat ribuan bidang
            // yang belum disentuh terhitung sebagai pekerjaan berjalan.
            if ($this->tgl_diproses === null) {
                return StatusBidang::BelumDiproses;
            }

            return StatusBidang::Proses;
        })->withoutObjectCaching();
    }

    /**
     * Bidang yang sudah dipilih untuk dikerjakan, bukan sekadar tercatat di
     * daftar aset.
     *
     * @param  Builder<Bidang>  $query
     * @return Builder<Bidang>
     */
    public function scopeDipantau(Builder $query, bool $dipantau = true): Builder
    {
        return $dipantau
            ? $query->whereNotNull('tgl_diproses')
            : $query->whereNull('tgl_diproses');
    }

    /**
     * Kondisi berjalan bidang ini, yaitu apa yang sedang ditunggu. Dipakai di
     * daftar bidang, tabel bidang terlama, dan label grafik — lihat aturan
     * pemakaian label di docs/spec.md bagian 6.
     *
     * @return Attribute<string, never>
     */
    protected function kondisiTahap(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->tahapBerikut?->labelMenunggu ?? self::KONDISI_TUNTAS
        )->withoutObjectCaching();
    }

    /**
     * Letak bidang dalam satu baris. Kecamatan kerap kosong pada data lama
     * yang hanya mencatat nama desa, jadi pemisahnya ikut hilang alih-alih
     * menyisakan garis miring menggantung.
     *
     * @return Attribute<string, never>
     */
    protected function letak(): Attribute
    {
        return Attribute::make(
            get: fn (): string => filled($this->kecamatan)
                ? $this->desa.' / '.$this->kecamatan
                : (string) $this->desa
        )->withoutObjectCaching();
    }

    /**
     * Punya kendala yang belum ditutup.
     *
     * Relasi yang sudah di-eager load dipakai apa adanya supaya halaman daftar
     * dan dashboard tidak memicu query per baris.
     */
    public function adaKendalaAktif(): bool
    {
        if ($this->relationLoaded('kendalaAktif')) {
            return $this->kendalaAktif->isNotEmpty();
        }

        if ($this->relationLoaded('kendala')) {
            return $this->kendala->contains(fn (Kendala $kendala): bool => ! $kendala->selesai());
        }

        return $this->exists && $this->kendalaAktif()->exists();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        $casts = [
            'tahun_target' => 'integer',
            'luas_m2' => 'decimal:2',
            // Tanggal alas hak bukan tahap: ia menandai umur dokumen dasar
            // yang dibawa pemohon, bukan langkah yang dikerjakan kantor.
            'tgl_alas_hak' => 'date',
            // `tgl_diproses` satu-satunya kolom tanggal yang ditulis kode
            // aplikasi, bukan sekadar dibaca — karena itu tipenya di docblock
            // ditulis CarbonInterface: pabrik tanggal Laravel boleh
            // dikonfigurasi memakai CarbonImmutable, dan keduanya sah disimpan.
            'tgl_diproses' => 'date',
            'status' => StatusBidang::class,
        ];

        foreach (Tahapan::semua() as $tahap) {
            $casts[$tahap->kolom] = 'date';
        }

        return $casts;
    }
}

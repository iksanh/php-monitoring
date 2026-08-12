<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\JenisInstansiFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Master jenis instansi — dikelola admin, bukan daftar tetap di kode.
 *
 * @property int $id
 * @property string $kode
 * @property string $nama
 * @property bool $aktif
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Instansi> $instansi
 */
class JenisInstansi extends Model
{
    /** @use HasFactory<JenisInstansiFactory> */
    use HasFactory;

    protected $table = 'jenis_instansi';

    protected $fillable = [
        'kode',
        'nama',
        'aktif',
    ];

    /**
     * Kode dibuat sekali dari nama lalu dikunci. Mengganti nama tidak boleh
     * mengubah kode, karena kode yang dipakai kode aplikasi untuk menemukan
     * jenis bawaan.
     */
    protected static function booted(): void
    {
        static::creating(function (JenisInstansi $jenis): void {
            if (blank($jenis->kode)) {
                $jenis->kode = $jenis->kodeUnik(Str::slug($jenis->nama, '_'));
            }
        });
    }

    /**
     * @return HasMany<Instansi, $this>
     */
    public function instansi(): HasMany
    {
        return $this->hasMany(Instansi::class);
    }

    /**
     * @param  Builder<JenisInstansi>  $query
     * @return Builder<JenisInstansi>
     */
    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('aktif', true);
    }

    public function terpakai(): bool
    {
        return $this->instansi()->exists();
    }

    private function kodeUnik(string $dasar): string
    {
        $dasar = $dasar !== '' ? $dasar : 'jenis';
        $kode = $dasar;
        $urutan = 1;

        while (static::query()->where('kode', $kode)->exists()) {
            $kode = $dasar.'_'.(++$urutan);
        }

        return $kode;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
        ];
    }
}

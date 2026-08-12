<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\InstansiFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $nama
 * @property int $jenis_instansi_id
 * @property-read JenisInstansi $jenis
 * @property bool $aktif
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Bidang> $bidang
 * @property-read Collection<int, User> $pengguna
 */
class Instansi extends Model
{
    /** @use HasFactory<InstansiFactory> */
    use HasFactory;

    protected $table = 'instansi';

    protected $fillable = [
        'nama',
        'jenis_instansi_id',
        'aktif',
    ];

    /**
     * Jenis instansi kini master data, bukan daftar tetap di kode.
     *
     * @return BelongsTo<JenisInstansi, $this>
     */
    public function jenis(): BelongsTo
    {
        return $this->belongsTo(JenisInstansi::class, 'jenis_instansi_id');
    }

    /**
     * @return HasMany<Bidang, $this>
     */
    public function bidang(): HasMany
    {
        return $this->hasMany(Bidang::class);
    }

    /**
     * @return HasMany<User, $this>
     */
    public function pengguna(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @param  Builder<Instansi>  $query
     * @return Builder<Instansi>
     */
    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('aktif', true);
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

<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\KategoriKendala;
use App\Observers\KendalaObserver;
use Database\Factories\KendalaFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $bidang_id
 * @property KategoriKendala $kategori
 * @property string $uraian
 * @property Carbon $tanggal_catat
 * @property Carbon|null $tanggal_selesai
 * @property string $dicatat_oleh
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Bidang $bidang
 */
#[ObservedBy(KendalaObserver::class)]
class Kendala extends Model
{
    /** @use HasFactory<KendalaFactory> */
    use HasFactory;

    protected $table = 'kendala';

    protected $fillable = [
        'bidang_id',
        'kategori',
        'uraian',
        'tanggal_catat',
        'tanggal_selesai',
        'dicatat_oleh',
    ];

    /**
     * @return BelongsTo<Bidang, $this>
     */
    public function bidang(): BelongsTo
    {
        return $this->belongsTo(Bidang::class);
    }

    public function selesai(): bool
    {
        return $this->tanggal_selesai !== null;
    }

    /**
     * Kendala yang belum ditutup.
     *
     * @param  Builder<Kendala>  $query
     * @return Builder<Kendala>
     */
    public function scopeAktif(Builder $query): Builder
    {
        return $query->whereNull('tanggal_selesai');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kategori' => KategoriKendala::class,
            'tanggal_catat' => 'date',
            'tanggal_selesai' => 'date',
        ];
    }
}

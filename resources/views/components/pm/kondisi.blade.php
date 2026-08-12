@props(['bidang'])

{{--
    Kondisi berjalan sebuah bidang: apa yang sedang ditunggu (label_menunggu
    tahap berikut), atau "Sudah Diserahkan" bila seluruh tahap terisi.

    Bidang berkendala aktif diberi badge kategori di sampingnya — lihat
    docs/spec.md bagian 6. Relasi kendalaAktif diharapkan sudah di-eager load
    oleh pemanggilnya.
--}}
@php
    $kendala = $bidang->kendalaAktif->first();
@endphp

<span class="inline-flex flex-wrap items-baseline gap-1.5">
    <span>{{ $bidang->kondisiTahap }}</span>

    @if ($kendala)
        <span class="rounded bg-amber-100 px-1.5 py-0.5 text-xs font-medium text-amber-900"
              title="Kendala aktif sejak {{ \App\Support\Tanggal::pendek($kendala->tanggal_catat) }}">
            {{ mb_strtolower($kendala->kategori->label()) }}
        </span>
    @endif
</span>

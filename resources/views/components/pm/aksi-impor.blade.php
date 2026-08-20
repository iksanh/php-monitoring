@props(['aksi'])

{{-- Penanda tindakan satu baris pratinjau impor: bidang baru atau timpa data
     lama. Warnanya sengaja berbeda tegas — "perbarui" menghapus isi lama, jadi
     harus terbaca sekilas. --}}
@php
    $perbarui = $aksi === \App\Support\Impor\BarisImpor::PERBARUI;
@endphp

<span {{ $attributes->class([
    'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
    'bg-amber-100 text-amber-800' => $perbarui,
    'bg-emerald-100 text-emerald-800' => ! $perbarui,
]) }}>
    {{ $perbarui ? 'Perbarui' : 'Tambah' }}
</span>

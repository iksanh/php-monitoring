@extends('layouts.pemantauan', ['judul' => 'Daftar Bidang'])

@php
    $pilihanTahap = [\App\Support\Filter\FilterBidang::BELUM_MULAI => 'Belum mulai'];
    foreach ($tahapan as $tahap) {
        $pilihanTahap[$tahap->kolom] = $tahap->urutan.'. '.$tahap->label;
    }
@endphp

@section('isi')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold sm:text-xl">Daftar Bidang</h1>
            <p class="text-sm text-zinc-500">
                {{ $daftar->total() }} bidang
                {{ $filter->adaFilter() ? 'sesuai penyaringan' : 'terdaftar' }}.
            </p>
        </div>

        <div class="flex w-full items-center gap-2 sm:w-auto">
            <a href="{{ route('bidang.export', $filter->queryString()) }}"
               class="flex-1 rounded-md border border-zinc-300 px-4 py-2.5 text-center text-sm font-medium text-zinc-700 hover:bg-zinc-50 sm:flex-none">
                Export Excel
            </a>

            @can('create', \App\Models\Bidang::class)
                <a href="{{ route('bidang.create') }}"
                   class="flex-1 rounded-md bg-zinc-900 px-4 py-2.5 text-center text-sm font-medium text-white hover:bg-zinc-700 sm:flex-none">
                    Tambah bidang
                </a>
            @endcan
        </div>
    </div>

    <form method="GET" action="{{ route('bidang.index') }}"
          class="mb-4 grid gap-3 rounded-lg border border-zinc-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-7">
        <div class="lg:col-span-2">
            <label for="cari" class="block text-xs font-medium text-zinc-600">Pencarian</label>
            <input type="search" name="cari" id="cari" value="{{ $filter->cari }}"
                   placeholder="Nama aset atau nomor urut"
                   class="mt-1 block w-full rounded-md border border-zinc-300 px-3 py-2 text-sm shadow-sm">
        </div>

        @foreach ([
            ['instansi', 'Instansi', $instansi->pluck('nama', 'id')->all(), $filter->instansiId],
            ['tahap', 'Tahap aktif', $pilihanTahap, $filter->tahapAktif],
            ['penanggung_jawab', 'Penanggung jawab', \App\Enums\PenanggungJawab::pilihan(), $filter->penanggungJawab?->value],
            ['status', 'Status', \App\Enums\StatusBidang::pilihan(), $filter->status?->value],
            ['tahun', 'Tahun target', array_combine($tahunTersedia, $tahunTersedia), $filter->tahunTarget],
        ] as [$nama, $label, $pilihan, $terpilih])
            <div>
                <label for="{{ $nama }}" class="block text-xs font-medium text-zinc-600">{{ $label }}</label>
                <select name="{{ $nama }}" id="{{ $nama }}"
                        class="mt-1 block w-full rounded-md border border-zinc-300 px-3 py-2 text-sm shadow-sm">
                    <option value="">Semua</option>
                    @foreach ($pilihan as $nilai => $teks)
                        <option value="{{ $nilai }}" @selected((string) $terpilih === (string) $nilai)>{{ $teks }}</option>
                    @endforeach
                </select>
            </div>
        @endforeach

        <div class="flex flex-wrap items-center gap-3 sm:col-span-2 lg:col-span-7">
            <button type="submit" class="w-full rounded-md bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-zinc-700 sm:w-auto">
                Terapkan
            </button>

            @if ($filter->adaFilter())
                <a href="{{ route('bidang.index') }}" class="text-sm font-medium text-zinc-600 hover:text-zinc-900">
                    Bersihkan penyaringan
                </a>
            @endif
        </div>
    </form>

    {{-- Ponsel dan tablet: satu kartu per bidang. Tabel sepuluh kolom tidak terbaca di layar sempit. --}}
    <div class="space-y-3 lg:hidden">
        @forelse ($daftar as $bidang)
            <div class="rounded-lg border border-zinc-200 bg-white p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <a href="{{ route('bidang.show', $bidang) }}" class="block font-medium underline-offset-2 hover:underline">
                            {{ $bidang->nama_aset }}
                        </a>
                        <p class="text-xs text-zinc-500">{{ $bidang->nomor_urut }}</p>
                    </div>
                    <x-pm.badge :status="$bidang->status" />
                </div>

                <dl class="mt-3 space-y-1.5 text-sm">
                    <div class="flex justify-between gap-3">
                        <dt class="text-zinc-500">Instansi</dt>
                        <dd class="text-right">{{ $bidang->instansi->nama }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-zinc-500">Letak</dt>
                        <dd class="text-right">{{ $bidang->desa }} / {{ $bidang->kecamatan }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-zinc-500">Luas</dt>
                        <dd class="text-right tabular-nums">
                            {{ $bidang->luas_m2 !== null ? number_format((float) $bidang->luas_m2, 2, ',', '.').' m²' : '—' }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-zinc-500">Tahap aktif</dt>
                        <dd class="text-right">{{ $bidang->tahapAktif?->label ?? 'Belum mulai' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-zinc-500">Penanggung jawab</dt>
                        <dd class="text-right">{{ $bidang->penanggungJawab?->label() ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-zinc-500">Umur</dt>
                        <dd class="text-right tabular-nums">{{ $bidang->umurHari !== null ? $bidang->umurHari.' hari' : '—' }}</dd>
                    </div>
                </dl>

                <div class="mt-3 flex gap-2 border-t border-zinc-100 pt-3">
                    <a href="{{ route('bidang.show', $bidang) }}"
                       class="flex-1 rounded-md border border-zinc-300 px-3 py-2 text-center text-sm font-medium text-zinc-700">
                        Lihat
                    </a>
                    @can('update', $bidang)
                        <a href="{{ route('bidang.edit', $bidang) }}"
                           class="flex-1 rounded-md border border-zinc-300 px-3 py-2 text-center text-sm font-medium text-zinc-700">
                            Ubah
                        </a>
                    @endcan
                </div>
            </div>
        @empty
            <div class="rounded-lg border border-zinc-200 bg-white px-4 py-8 text-center text-sm text-zinc-500">
                {{ $filter->adaFilter() ? 'Tidak ada bidang yang cocok dengan penyaringan ini.' : 'Belum ada bidang.' }}
            </div>
        @endforelse
    </div>

    {{-- Desktop: tabel penuh. --}}
    <div class="hidden overflow-x-auto rounded-lg border border-zinc-200 bg-white lg:block">
        <table class="min-w-full divide-y divide-zinc-200 text-sm">
            <thead class="bg-zinc-50 text-left text-xs uppercase tracking-wide text-zinc-500">
                <tr>
                    <th class="px-4 py-3">Nomor urut</th>
                    <th class="px-4 py-3">Nama aset</th>
                    <th class="px-4 py-3">Instansi</th>
                    <th class="px-4 py-3">Desa / Kecamatan</th>
                    <th class="hidden px-4 py-3 text-right xl:table-cell">Luas (m²)</th>
                    <th class="px-4 py-3">Tahap aktif</th>
                    <th class="px-4 py-3">Penanggung jawab</th>
                    <th class="px-4 py-3 text-right">Umur</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100">
                @forelse ($daftar as $bidang)
                    <tr class="hover:bg-zinc-50">
                        <td class="px-4 py-3 font-medium">
                            <a href="{{ route('bidang.show', $bidang) }}" class="text-zinc-900 underline-offset-2 hover:underline">
                                {{ $bidang->nomor_urut }}
                            </a>
                        </td>
                        <td class="px-4 py-3">{{ $bidang->nama_aset }}</td>
                        <td class="px-4 py-3 text-zinc-600">{{ $bidang->instansi->nama }}</td>
                        <td class="px-4 py-3 text-zinc-600">{{ $bidang->desa }} / {{ $bidang->kecamatan }}</td>
                        <td class="hidden px-4 py-3 text-right tabular-nums text-zinc-600 xl:table-cell">
                            {{ $bidang->luas_m2 !== null ? number_format((float) $bidang->luas_m2, 2, ',', '.') : '—' }}
                        </td>
                        <td class="px-4 py-3">{{ $bidang->tahapAktif?->label ?? 'Belum mulai' }}</td>
                        <td class="px-4 py-3 text-zinc-600">{{ $bidang->penanggungJawab?->label() ?? '—' }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $bidang->umurHari !== null ? $bidang->umurHari.' hari' : '—' }}</td>
                        <td class="px-4 py-3"><x-pm.badge :status="$bidang->status" /></td>
                        <td class="px-4 py-3 text-right">
                            @can('update', $bidang)
                                <a href="{{ route('bidang.edit', $bidang) }}" class="text-sm font-medium text-zinc-700 hover:text-zinc-900">Ubah</a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="px-4 py-8 text-center text-zinc-500">
                            {{ $filter->adaFilter() ? 'Tidak ada bidang yang cocok dengan penyaringan ini.' : 'Belum ada bidang.' }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $daftar->links() }}
    </div>
@endsection

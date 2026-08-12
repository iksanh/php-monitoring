@extends('layouts.pemantauan', ['judul' => 'Dashboard'])

@php
    // Slot warna kategorikal: biru untuk Kantor Pertanahan, jingga untuk Pemohon.
    // Pasangan ini lolos seluruh pemeriksaan keterbacaan buta warna.
    $biru = '#2a78d6';
    $jingga = '#eb6834';
    $abu = '#e5e5e5';

    $grafikTertahan = [
        'bentuk' => 'tertahan',
        'label' => array_map(fn ($baris) => $baris->tahap->label, $sebaran),
        'nilai' => array_map(fn ($baris) => $baris->jumlah, $sebaran),
        'warna' => $biru,
    ];

    $totalTertahan = array_sum($grafikTertahan['nilai']);

    $grafikPenanggungJawab = [
        'bentuk' => 'penanggungJawab',
        'label' => array_map(
            fn (string $nilai) => \App\Enums\PenanggungJawab::from($nilai)->label(),
            array_keys($perPenanggungJawab),
        ),
        'nilai' => array_values($perPenanggungJawab),
        'warna' => [$biru, $jingga],
        'inti' => ['angka' => (string) $totalTertahan, 'keterangan' => 'bidang tertahan'],
    ];

    $grafikCapaian = [
        'bentuk' => 'capaian',
        'label' => array_map(fn ($baris) => $baris->nama, $capaian),
        'selesai' => array_map(fn ($baris) => $baris->bersertipikat, $capaian),
        'belum' => array_map(fn ($baris) => $baris->total - $baris->bersertipikat, $capaian),
        'warna' => [$biru, $abu],
    ];
@endphp

@section('isi')
    <div class="mb-5 flex flex-wrap items-end justify-between gap-3 sm:mb-6">
        <div>
            <h1 class="text-lg font-semibold sm:text-xl">Dashboard</h1>
            <p class="text-sm text-zinc-500">Capaian sertipikasi Hak Pakai target tahun {{ $tahun }}.</p>
        </div>

        <div class="text-sm {{ $dataBasi ? 'rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-amber-900' : 'text-zinc-500' }}">
            @if ($dataBasi)
                <span class="font-semibold">Perhatian:</span>
                data belum dimutakhirkan sejak
                {{ \App\Support\Tanggal::pendek($pemutakhiran) }}
                (lebih dari {{ \App\Services\DashboardService::BATAS_BASI_HARI }} hari lalu).
            @else
                Pemutakhiran data terakhir: <span class="font-medium text-zinc-700">{{ \App\Support\Tanggal::waktu($pemutakhiran) }}</span>
            @endif
        </div>
    </div>

    {{-- Kartu pertama melebar penuh di ponsel supaya empat sisanya jatuh rapi
         dua-dua, bukan menyisakan satu kartu menggantung. --}}
    <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-5">
        @foreach ([
            ['Target ' . $tahun, $kartu->total, 'bidang ditargetkan tahun ini', 'text-zinc-900'],
            ['Sudah bersertipikat', $kartu->bersertipikat, 'sertipikat sudah terbit', 'text-emerald-700'],
            ['Sudah diserahkan', $kartu->diserahkan, 'aset diserahkan ke pemilik', 'text-sky-700'],
            ['Dalam proses', $kartu->proses, 'masih berjalan', 'text-amber-700'],
            ['Terkendala', $kartu->terkendala, 'ditandai bermasalah', 'text-red-700'],
        ] as [$judulKartu, $angka, $keterangan, $warnaAngka])
            <div class="rounded-lg border border-zinc-200 bg-white p-4 sm:p-5 {{ $loop->first ? 'col-span-2 lg:col-span-1' : '' }}">
                <p class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ $judulKartu }}</p>
                <p class="mt-2 text-2xl font-semibold tabular-nums sm:text-3xl {{ $warnaAngka }}">{{ $angka }}</p>
                <p class="mt-1 text-xs text-zinc-500">{{ $keterangan }}</p>
            </div>
        @endforeach
    </div>

    <div class="mt-4 rounded-lg border border-zinc-200 bg-white p-4 sm:p-5">
        <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
            <p class="text-sm font-medium text-zinc-700">Capaian terhadap target {{ $tahun }}</p>
            <p class="flex items-baseline gap-2 text-sm text-zinc-500">
                <span class="text-xl font-semibold tabular-nums text-zinc-900 sm:text-lg">{{ $kartu->persenCapaian() }}%</span>
                <span>{{ $kartu->bersertipikat }} dari {{ $kartu->total }} bidang</span>
            </p>
        </div>

        <div class="mt-3 h-3 w-full overflow-hidden rounded-full bg-zinc-200">
            <div class="h-full rounded-full transition-[width] duration-500"
                 style="width: {{ max($kartu->persenCapaian(), 1) }}%; background-color: {{ $biru }}"
                 role="progressbar"
                 aria-valuenow="{{ $kartu->persenCapaian() }}" aria-valuemin="0" aria-valuemax="100"
                 aria-label="Capaian terhadap target {{ $tahun }}"></div>
        </div>
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-3">
        <section class="rounded-lg border border-zinc-200 bg-white p-4 sm:p-5 lg:col-span-2">
            <h2 class="text-sm font-semibold text-zinc-700">Bidang tertahan di tiap tahap</h2>
            <p class="text-xs text-zinc-500">Seluruh bidang berjalan, dihitung dari tahap berikutnya yang belum selesai.</p>

            <div class="mt-4 h-72 sm:h-80">
                <canvas data-grafik="{{ json_encode($grafikTertahan) }}"></canvas>
            </div>

            <details class="mt-3 text-sm">
                <summary class="cursor-pointer py-1 text-zinc-500 hover:text-zinc-800">Lihat angkanya</summary>
                <div class="overflow-x-auto">
                <table class="mt-3 w-full min-w-lg text-sm">
                    <thead class="text-left text-xs uppercase tracking-wide text-zinc-500">
                        <tr>
                            <th class="py-1">Tahap</th>
                            <th class="py-1">Unit pelaksana</th>
                            <th class="py-1">Penanggung jawab</th>
                            <th class="py-1 text-right">Bidang</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @foreach ($sebaran as $baris)
                            <tr>
                                <td class="py-1.5">{{ $baris->tahap->label }}</td>
                                <td class="py-1.5 text-zinc-500">{{ $baris->tahap->unit }}</td>
                                <td class="py-1.5 text-zinc-500">{{ $baris->tahap->penanggungJawab->label() }}</td>
                                <td class="py-1.5 text-right tabular-nums">{{ $baris->jumlah }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
            </details>
        </section>

        <section class="rounded-lg border border-zinc-200 bg-white p-4 sm:p-5">
            <h2 class="text-sm font-semibold text-zinc-700">Bola ada di siapa</h2>
            <p class="text-xs text-zinc-500">Bidang tertahan menurut penanggung jawab tahap berikutnya.</p>

            <div class="mt-4 h-56 sm:h-64">
                <canvas data-grafik="{{ json_encode($grafikPenanggungJawab) }}"></canvas>
            </div>

            <ul class="mt-3 space-y-1 text-sm">
                @foreach ($perPenanggungJawab as $nilai => $jumlah)
                    <li class="flex items-center justify-between gap-3">
                        <span class="flex items-center gap-2">
                            <span class="size-2.5 rounded-full" style="background-color: {{ $loop->first ? $biru : $jingga }}"></span>
                            {{ \App\Enums\PenanggungJawab::from($nilai)->label() }}
                        </span>
                        <span class="tabular-nums text-zinc-600">
                            {{ $jumlah }}{{ $totalTertahan > 0 ? ' ('.round($jumlah / $totalTertahan * 100).'%)' : '' }}
                        </span>
                    </li>
                @endforeach
            </ul>
        </section>
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-2 lg:items-start">
        <section class="rounded-lg border border-zinc-200 bg-white p-4 sm:p-5">
            <h2 class="text-sm font-semibold text-zinc-700">Capaian per instansi pemilik aset</h2>
            <p class="text-xs text-zinc-500">Target tahun {{ $tahun }}.</p>

            {{-- Ponsel: nama instansi terlalu panjang untuk sumbu grafik, jadi
                 diberi barisnya sendiri di atas batang. --}}
            <div class="mt-4 space-y-4 sm:hidden">
                @forelse ($capaian as $baris)
                    <div>
                        <div class="flex items-baseline justify-between gap-3 text-sm">
                            <span class="font-medium">{{ $baris->nama }}</span>
                            <span class="shrink-0 tabular-nums text-zinc-600">{{ $baris->persen() }}%</span>
                        </div>
                        <div class="mt-1.5 h-2.5 w-full overflow-hidden rounded-full bg-zinc-200">
                            <div class="h-full rounded-full" style="width: {{ $baris->persen() }}%; background-color: {{ $biru }}"></div>
                        </div>
                        <p class="mt-1 text-xs text-zinc-500">
                            {{ $baris->bersertipikat }} dari {{ $baris->total }} bidang bersertipikat
                        </p>
                    </div>
                @empty
                    <p class="text-sm text-zinc-500">Belum ada bidang pada tahun target ini.</p>
                @endforelse
            </div>

            <div class="mt-4 hidden h-64 sm:block">
                <canvas data-grafik="{{ json_encode($grafikCapaian) }}"></canvas>
            </div>
        </section>

        <section class="rounded-lg border border-zinc-200 bg-white p-4 sm:p-5">
            <h2 class="text-sm font-semibold text-zinc-700">Sepuluh bidang terlama belum selesai</h2>
            <p class="text-xs text-zinc-500">Dihitung sejak {{ mb_strtolower(\App\Support\Tahapan::pertama()->label) }}, sertipikat belum terbit.</p>

            {{-- Ponsel: ringkasan per bidang, tanpa tabel enam kolom. --}}
            <ul class="mt-4 divide-y divide-zinc-100 sm:hidden">
                @forelse ($terlama as $bidang)
                    <li class="py-3">
                        <div class="flex items-start justify-between gap-3">
                            <a href="{{ route('bidang.show', $bidang) }}" class="min-w-0 font-medium underline-offset-2 hover:underline">
                                {{ $bidang->nama_aset }}
                            </a>
                            <span class="shrink-0 text-sm font-medium tabular-nums">{{ $bidang->umurHari }} hari</span>
                        </div>
                        <p class="mt-1 text-xs text-zinc-500">{{ $bidang->instansi->nama }}</p>
                        <p class="mt-1 text-xs text-zinc-600">
                            {{ $bidang->tahapAktif?->label ?? 'Belum mulai' }}
                            <span class="text-zinc-400">→</span>
                            {{ $bidang->tahapBerikut?->label ?? '—' }}
                            <span class="text-zinc-400">·</span>
                            {{ $bidang->penanggungJawab?->label() ?? '—' }}
                        </p>
                    </li>
                @empty
                    <li class="py-6 text-center text-sm text-zinc-500">Tidak ada bidang yang tertunda.</li>
                @endforelse
            </ul>

            <div class="mt-4 hidden overflow-x-auto sm:block">
                <table class="min-w-full text-sm">
                    <thead class="text-left text-xs uppercase tracking-wide text-zinc-500">
                        <tr>
                            <th class="py-2">Nama aset</th>
                            <th class="py-2">Instansi</th>
                            <th class="py-2">Tahap aktif</th>
                            <th class="hidden py-2 xl:table-cell">Tahap berikut</th>
                            <th class="hidden py-2 xl:table-cell">Penanggung jawab</th>
                            <th class="py-2 text-right">Umur</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @forelse ($terlama as $bidang)
                            <tr>
                                <td class="py-2">
                                    <a href="{{ route('bidang.show', $bidang) }}" class="font-medium underline-offset-2 hover:underline">
                                        {{ $bidang->nama_aset }}
                                    </a>
                                </td>
                                <td class="py-2 text-zinc-500">{{ $bidang->instansi->nama }}</td>
                                <td class="py-2">{{ $bidang->tahapAktif?->label ?? 'Belum mulai' }}</td>
                                <td class="hidden py-2 xl:table-cell">{{ $bidang->tahapBerikut?->label ?? '—' }}</td>
                                <td class="hidden py-2 text-zinc-500 xl:table-cell">{{ $bidang->penanggungJawab?->label() ?? '—' }}</td>
                                <td class="py-2 text-right font-medium tabular-nums">{{ $bidang->umurHari }} hari</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-6 text-center text-zinc-500">Tidak ada bidang yang tertunda.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection

@push('skrip')
    @vite('resources/js/dashboard.js')
@endpush

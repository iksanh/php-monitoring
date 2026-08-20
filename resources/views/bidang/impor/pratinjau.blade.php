@extends('layouts.pemantauan', ['judul' => 'Periksa Berkas Impor'])

@php
    $galat = $hasil->barisGalat();
    $sah = $hasil->barisSah();
    $total = count($hasil->baris);
    $bersih = $hasil->siapDisimpan();

    // Tabel pratinjau dipotong: berkas seribu baris akan membuat halaman ini
    // berat dibuka, sementara nilainya sebagai pemeriksaan sudah tercapai dari
    // ringkasan angka di atasnya.
    $batasTampil = 100;
@endphp

@section('isi')
    <div class="mb-4">
        <a href="{{ route('bidang.impor.form') }}" class="inline-flex items-center gap-1 text-sm text-zinc-500 hover:text-zinc-900">
            <svg class="size-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
            </svg>
            Impor Data Bidang
        </a>
        <h1 class="mt-1 text-lg font-semibold sm:text-xl">Hasil pemeriksaan berkas</h1>
        <p class="mt-1 break-all text-sm text-zinc-500">
            {{ $namaBerkas }} · {{ number_format($total, 0, ',', '.') }} baris data
            @if ($perbarui)
                · mode perbarui aktif
            @endif
        </p>
    </div>

    <div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach ([
            ['Baris terbaca', $total, 'text-zinc-900', 'border-zinc-200'],
            ['Akan ditambahkan', $hasil->jumlahTambah(), 'text-emerald-700', 'border-emerald-200'],
            ['Akan diperbarui', $hasil->jumlahPerbarui(), 'text-amber-700', 'border-amber-200'],
            ['Bermasalah', $hasil->jumlahGalat(), $hasil->jumlahGalat() > 0 ? 'text-red-700' : 'text-zinc-400', $hasil->jumlahGalat() > 0 ? 'border-red-200' : 'border-zinc-200'],
        ] as [$label, $angka, $warna, $garis])
            <div class="rounded-lg border bg-white p-4 {{ $garis }}">
                <div class="text-xs text-zinc-500">{{ $label }}</div>
                <div class="mt-1 text-2xl font-semibold tabular-nums {{ $warna }}">
                    {{ number_format($angka, 0, ',', '.') }}
                </div>
            </div>
        @endforeach
    </div>

    @if ($bersih)
        <div class="mb-4 flex items-start gap-3 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3">
            <svg class="mt-0.5 size-5 shrink-0 text-emerald-600" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
            <div class="text-sm text-emerald-900">
                <p class="font-medium">Berkas siap disimpan.</p>
                <p class="mt-0.5">Seluruh baris lolos pemeriksaan. Belum ada yang tersimpan sampai Anda menekan tombol konfirmasi.</p>
            </div>
        </div>
    @else
        <div class="mb-4 flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3">
            <svg class="mt-0.5 size-5 shrink-0 text-red-600" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
            <div class="text-sm text-red-900">
                <p class="font-medium">{{ number_format(count($galat), 0, ',', '.') }} baris perlu dibetulkan dulu.</p>
                <p class="mt-0.5">
                    Tidak ada satu baris pun yang disimpan selama masih ada yang bermasalah —
                    supaya Anda tidak perlu menebak mana yang sudah masuk dan mana yang belum.
                    Betulkan berkasnya di Excel, lalu unggah ulang.
                </p>
            </div>
        </div>

        <section class="mb-4 overflow-hidden rounded-lg border border-zinc-200 bg-white">
            <h2 class="border-b border-zinc-100 px-4 py-3 text-sm font-semibold">Rincian masalah per baris</h2>

            <ul class="divide-y divide-zinc-100">
                @foreach ($galat as $baris)
                    <li class="px-4 py-3">
                        <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                            <span class="rounded bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-800">
                                Baris {{ $baris->nomorBaris }}
                            </span>
                            <span class="text-sm font-medium">{{ $baris->nomorUrut !== '' ? $baris->nomorUrut : '(nomor urut kosong)' }}</span>
                            @if ($baris->namaAset !== '')
                                <span class="text-sm text-zinc-500">— {{ $baris->namaAset }}</span>
                            @endif
                        </div>

                        <ul class="mt-1.5 list-inside list-disc space-y-0.5 text-sm text-zinc-700">
                            @foreach ($baris->galat as $pesan)
                                <li>{{ $pesan }}</li>
                            @endforeach
                        </ul>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($sah !== [])
        <section class="mb-4 overflow-hidden rounded-lg border border-zinc-200 bg-white">
            <h2 class="flex flex-wrap items-baseline justify-between gap-2 border-b border-zinc-100 px-4 py-3">
                <span class="text-sm font-semibold">Baris yang lolos pemeriksaan</span>
                @if (count($sah) > $batasTampil)
                    <span class="text-xs text-zinc-500">
                        menampilkan {{ $batasTampil }} dari {{ number_format(count($sah), 0, ',', '.') }} baris
                    </span>
                @endif
            </h2>

            {{-- Ponsel dan tablet: kartu, sejalan dengan daftar bidang. --}}
            <ul class="divide-y divide-zinc-100 lg:hidden">
                @foreach (array_slice($sah, 0, $batasTampil) as $baris)
                    <li class="px-4 py-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium">{{ $baris->namaAset }}</p>
                                <p class="text-xs text-zinc-500">Baris {{ $baris->nomorBaris }} · {{ $baris->nomorUrut }}</p>
                            </div>
                            <x-pm.aksi-impor :aksi="$baris->aksi()" />
                        </div>
                        <dl class="mt-2 space-y-1 text-sm">
                            <div class="flex justify-between gap-3">
                                <dt class="text-zinc-500">Instansi</dt>
                                <dd class="text-right">{{ $baris->instansi }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-zinc-500">Tahun target</dt>
                                <dd class="text-right tabular-nums">{{ $baris->nilai['tahun_target'] }}</dd>
                            </div>
                        </dl>
                    </li>
                @endforeach
            </ul>

            <div class="hidden overflow-x-auto lg:block">
                <table class="min-w-full divide-y divide-zinc-200 text-sm">
                    <thead class="bg-zinc-50 text-left text-xs uppercase tracking-wide text-zinc-500">
                        <tr>
                            <th class="px-4 py-2.5">Baris</th>
                            <th class="px-4 py-2.5">Nomor urut</th>
                            <th class="px-4 py-2.5">Nama aset</th>
                            <th class="px-4 py-2.5">Instansi</th>
                            <th class="px-4 py-2.5">Desa / Kecamatan</th>
                            <th class="px-4 py-2.5 text-right">Tahun</th>
                            <th class="px-4 py-2.5">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @foreach (array_slice($sah, 0, $batasTampil) as $baris)
                            <tr>
                                <td class="px-4 py-2.5 tabular-nums text-zinc-500">{{ $baris->nomorBaris }}</td>
                                <td class="px-4 py-2.5 font-medium">{{ $baris->nomorUrut }}</td>
                                <td class="px-4 py-2.5">{{ $baris->namaAset }}</td>
                                <td class="px-4 py-2.5 text-zinc-600">{{ $baris->instansi }}</td>
                                <td class="px-4 py-2.5 text-zinc-600">
                                    {{ trim(($baris->nilai['desa'] ?? '').' / '.($baris->nilai['kecamatan'] ?? ''), ' /') }}
                                </td>
                                <td class="px-4 py-2.5 text-right tabular-nums">{{ $baris->nilai['tahun_target'] }}</td>
                                <td class="px-4 py-2.5"><x-pm.aksi-impor :aksi="$baris->aksi()" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    <div class="flex flex-col gap-2 rounded-lg border border-zinc-200 bg-white p-4 sm:flex-row-reverse sm:items-center sm:justify-start">
        @if ($bersih)
            <form method="POST" action="{{ route('bidang.impor.simpan') }}" class="sm:w-auto">
                @csrf
                <button type="submit"
                        class="w-full rounded-md bg-zinc-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-zinc-700 sm:w-auto">
                    Simpan {{ number_format(count($sah), 0, ',', '.') }} bidang
                </button>
            </form>
        @else
            <a href="{{ route('bidang.impor.form') }}"
               class="w-full rounded-md bg-zinc-900 px-5 py-2.5 text-center text-sm font-semibold text-white hover:bg-zinc-700 sm:w-auto">
                Unggah berkas yang sudah dibetulkan
            </a>
        @endif

        <form method="POST" action="{{ route('bidang.impor.batal') }}" class="sm:w-auto">
            @csrf
            <button type="submit"
                    class="w-full rounded-md border border-zinc-300 px-5 py-2.5 text-sm font-medium text-zinc-700 hover:bg-zinc-50 sm:w-auto">
                Batal
            </button>
        </form>
    </div>
@endsection

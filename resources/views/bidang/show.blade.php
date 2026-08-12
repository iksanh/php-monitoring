@extends('layouts.pemantauan', ['judul' => 'Bidang '.$bidang->nomor_urut])

@section('isi')
    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold sm:text-xl">{{ $bidang->nama_aset }}</h1>
            <p class="text-sm text-zinc-500">
                {{ $bidang->nomor_urut }} · {{ $bidang->instansi->nama }} · <x-pm.badge :status="$bidang->status" />
            </p>
        </div>

        <div class="flex w-full items-center gap-2 sm:w-auto">
            @can('update', $bidang)
                <a href="{{ route('bidang.edit', $bidang) }}"
                   class="flex-1 rounded-md bg-zinc-900 px-4 py-2.5 text-center text-sm font-medium text-white hover:bg-zinc-700 sm:flex-none">Ubah</a>
            @endcan

            @can('delete', $bidang)
                <form method="POST" action="{{ route('bidang.destroy', $bidang) }}"
                      onsubmit="return confirm('Arsipkan bidang {{ $bidang->nomor_urut }}?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="w-full rounded-md border border-red-300 px-4 py-2.5 text-sm font-medium text-red-700 hover:bg-red-50">
                        Arsipkan
                    </button>
                </form>
            @endcan
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <section class="rounded-lg border border-zinc-200 bg-white p-4 sm:p-5 lg:col-span-1">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500">Identitas</h2>

            <dl class="mt-4 space-y-3 text-sm">
                @foreach ([
                    'Penggunaan' => $bidang->penggunaan ?: '—',
                    'Desa' => $bidang->desa,
                    'Kecamatan' => $bidang->kecamatan,
                    'Luas' => $bidang->luas_m2 !== null ? number_format((float) $bidang->luas_m2, 2, ',', '.').' m²' : '—',
                    'Nomor berkas KKP' => $bidang->nomor_berkas_kkp ?: '—',
                    'Tahun target' => $bidang->tahun_target,
                    'Tahap aktif' => $bidang->tahapAktif?->label ?? 'Belum mulai',
                    'Tahap berikut' => $bidang->tahapBerikut?->label ?? 'Tuntas',
                    'Penanggung jawab' => $bidang->penanggungJawab?->label() ?? '—',
                    'Umur berkas' => $bidang->umurHari !== null ? $bidang->umurHari.' hari' : '—',
                    'Progres' => $bidang->persenProgres.'%',
                ] as $label => $nilai)
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">{{ $label }}</dt>
                        <dd class="text-right font-medium">{{ $nilai }}</dd>
                    </div>
                @endforeach
            </dl>

            @if ($bidang->keterangan)
                <p class="mt-4 border-t border-zinc-100 pt-4 text-sm text-zinc-600">{{ $bidang->keterangan }}</p>
            @endif
        </section>

        <section class="rounded-lg border border-zinc-200 bg-white p-4 sm:p-5 lg:col-span-2">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500">Tahapan</h2>

            <ol class="mt-4 space-y-1">
                @foreach ($tahapan as $tahap)
                    @php
                        $berlaku = $bidang->tahapDipakai($tahap);
                        $tanggal = $bidang->tanggalTahap($tahap);
                        $selesai = $berlaku && $tanggal !== null;
                    @endphp

                    <li class="flex gap-4 border-l-2 pl-4 pb-4 {{ $selesai ? 'border-emerald-400' : 'border-zinc-200' }}">
                        <div class="flex-1">
                            <p class="text-sm font-medium {{ $berlaku ? ($selesai ? 'text-zinc-900' : 'text-zinc-400') : 'text-zinc-400 line-through' }}">
                                {{ $tahap->urutan }}. {{ $tahap->label }}
                            </p>
                            <p class="text-xs {{ $berlaku ? 'text-zinc-500' : 'text-zinc-400' }}">
                                {{ $tahap->unit }} · {{ $tahap->dokumen }}
                            </p>
                            @unless ($berlaku)
                                <p class="text-xs italic text-zinc-400">tidak berlaku untuk bidang ini</p>
                            @endunless
                        </div>

                        <div class="text-right text-sm {{ $selesai ? 'font-medium text-zinc-900' : 'text-zinc-400' }}">
                            {{ $berlaku ? \App\Support\Tanggal::pendek($tanggal) : '—' }}
                        </div>
                    </li>
                @endforeach
            </ol>
        </section>
    </div>

    <section class="mt-6 rounded-lg border border-zinc-200 bg-white p-4 sm:p-5">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500">Kendala</h2>

        <ul class="mt-4 divide-y divide-zinc-100">
            @forelse ($bidang->kendala as $kendala)
                <li class="flex flex-wrap items-start justify-between gap-3 py-3">
                    <div class="max-w-2xl">
                        <p class="text-sm">{{ $kendala->uraian }}</p>
                        <p class="mt-1 text-xs text-zinc-500">
                            Dicatat {{ \App\Support\Tanggal::pendek($kendala->tanggal_catat) }} oleh {{ $kendala->dicatat_oleh }}
                            @if ($kendala->selesai())
                                · <span class="text-emerald-700">selesai {{ \App\Support\Tanggal::pendek($kendala->tanggal_selesai) }}</span>
                            @else
                                · <span class="text-red-700">masih terbuka</span>
                            @endif
                        </p>
                    </div>

                    @can('update', $kendala)
                        <div class="flex w-full items-center gap-2 sm:w-auto">
                            <a href="{{ route('kendala.edit', $kendala) }}" class="text-sm font-medium text-zinc-700 hover:text-zinc-900">Ubah</a>
                            <form method="POST" action="{{ route('kendala.destroy', $kendala) }}"
                                  onsubmit="return confirm('Hapus kendala ini?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-sm font-medium text-red-700 hover:text-red-900">Hapus</button>
                            </form>
                        </div>
                    @endcan
                </li>
            @empty
                <li class="py-3 text-sm text-zinc-500">Belum ada kendala tercatat.</li>
            @endforelse
        </ul>

        @can('create', \App\Models\Kendala::class)
            <form method="POST" action="{{ route('kendala.store', $bidang) }}" class="mt-5 grid gap-4 border-t border-zinc-100 pt-5 sm:grid-cols-2">
                @csrf
                <div class="sm:col-span-2">
                    <x-pm.textarea label="Uraian kendala" name="uraian" wajib />
                </div>
                <x-pm.input label="Tanggal catat" name="tanggal_catat" type="date"
                            :value="now()->format('Y-m-d')" wajib />
                <x-pm.input label="Dicatat oleh" name="dicatat_oleh" :value="auth()->user()->name" wajib />

                <div class="sm:col-span-2">
                    <button type="submit" class="w-full rounded-md bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-zinc-700 sm:w-auto">
                        Catat kendala
                    </button>
                </div>
            </form>
        @endcan
    </section>
@endsection

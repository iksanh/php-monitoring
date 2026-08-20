@extends('layouts.pemantauan', ['judul' => 'Daftar Bidang'])

@php
    $pilihanTahap = [\App\Support\Filter\FilterBidang::BELUM_MULAI => 'Belum mulai'];
    foreach ($tahapan as $tahap) {
        $pilihanTahap[$tahap->kolom] = $tahap->urutan.'. '.$tahap->label;
    }

    // Aksi massal hanya untuk peran yang boleh menulis; viewer melihat daftar
    // yang sama tanpa kotak centang.
    $bolehTandai = auth()->user()?->can('create', \App\Models\Bidang::class) ?? false;
    $tahunSekarang = (int) now()->format('Y');
    $kelasKotak = 'size-4 shrink-0 rounded border-zinc-300 text-zinc-900 focus:ring-zinc-500';
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

        {{-- Tiga tombol tidak muat sebaris di ponsel: dibiarkan melipat, dan
             tiap tombol tetap selebar minimal jempol. --}}
        <div class="flex w-full flex-wrap items-center gap-2 sm:w-auto">
            <a href="{{ route('bidang.export', $filter->queryString()) }}"
               class="min-w-40 grow rounded-md border border-zinc-300 px-4 py-2.5 text-center text-sm font-medium text-zinc-700 hover:bg-zinc-50 sm:min-w-0 sm:grow-0">
                Export Excel
            </a>

            @can('create', \App\Models\Bidang::class)
                <a href="{{ route('bidang.impor.form') }}"
                   class="min-w-40 grow rounded-md border border-zinc-300 px-4 py-2.5 text-center text-sm font-medium text-zinc-700 hover:bg-zinc-50 sm:min-w-0 sm:grow-0">
                    Impor Excel
                </a>

                <a href="{{ route('bidang.create') }}"
                   class="min-w-40 grow rounded-md bg-zinc-900 px-4 py-2.5 text-center text-sm font-medium text-white hover:bg-zinc-700 sm:min-w-0 sm:grow-0">
                    Tambah bidang
                </a>
            @endcan
        </div>
    </div>

    <form method="GET" action="{{ route('bidang.index') }}"
          class="mb-4 grid gap-3 rounded-lg border border-zinc-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="lg:col-span-2">
            <label for="cari" class="block text-xs font-medium text-zinc-600">Pencarian</label>
            <input type="search" name="cari" id="cari" value="{{ $filter->cari }}"
                   placeholder="Nama aset atau nomor urut"
                   class="mt-1 block w-full rounded-md border border-zinc-300 px-3 py-2 text-sm shadow-sm">
        </div>

        {{-- Desa diketik, bukan dipilih: nama desa pada daftar aset ditulis
             beragam, dan penggalan nama sudah cukup menemukannya. --}}
        <div>
            <label for="desa" class="block text-xs font-medium text-zinc-600">Desa</label>
            <input type="search" name="desa" id="desa" value="{{ $filter->desa }}"
                   placeholder="Ketik nama desa"
                   autocomplete="off"
                   class="mt-1 block w-full rounded-md border border-zinc-300 px-3 py-2 text-sm shadow-sm">
        </div>

        @foreach ([
            ['instansi', 'Instansi', $instansi->pluck('nama', 'id')->all(), $filter->instansiId],
            ['pantauan', 'Pantauan', \App\Support\Filter\FilterBidang::pilihanPantauan(), $filter->pantauan],
            ['pengguna_barang', 'Pengguna barang', array_combine($penggunaBarangTersedia, $penggunaBarangTersedia), $filter->penggunaBarang],
            ['tahap', 'Tahap aktif', $pilihanTahap, $filter->tahapAktif],
            ['penanggung_jawab', 'Penanggung jawab', \App\Enums\PenanggungJawab::pilihan(), $filter->penanggungJawab?->value],
            ['status', 'Status', \App\Enums\StatusBidang::pilihan(), $filter->status?->value],
            ['kendala', 'Kategori kendala', \App\Enums\KategoriKendala::pilihan(), $filter->kategoriKendala?->value],
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

        <div class="flex flex-wrap items-center gap-3 sm:col-span-2 lg:col-span-4">
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

    @if ($bolehTandai)
        {{--
            Satu form membungkus kartu ponsel dan tabel desktop sekaligus.
            Kotak centang bidang yang sama muncul di keduanya; yang tersembunyi
            tidak pernah ikut tercentang, dan id kembar tetap disaring di
            App\Http\Requests\ProsesBidangRequest.

            Penyaring aktif dititip di query string action, bukan di isian
            form, supaya "seluruh hasil penyaringan" membaca batasan yang sama
            dengan yang sedang dilihat operator.
        --}}
        <form method="POST" action="{{ route('bidang.proses', $filter->queryString()) }}"
              data-aksi-massal data-total="{{ $daftar->total() }}">
            @csrf

            <div class="mb-3 rounded-lg border border-zinc-200 bg-zinc-50 p-3 sm:p-4">
                <p class="text-sm font-medium text-zinc-700">Pilih bidang yang dikerjakan</p>
                <p class="mt-1 text-xs text-zinc-500">
                    Daftar aset memuat seluruh tanah pemda. Centang bidang yang benar-benar diproses
                    Kantor Pertanahan — hanya bidang itu yang dihitung di dashboard.
                </p>

                <div class="mt-3 flex flex-wrap items-end gap-3">
                    <label class="flex items-center gap-2 text-sm text-zinc-700">
                        <input type="checkbox" name="semua_hasil" value="1" class="{{ $kelasKotak }}" data-semua-hasil>
                        <span>Seluruh {{ number_format($daftar->total(), 0, ',', '.') }} hasil penyaringan</span>
                    </label>

                    <div>
                        <label for="tahun_target" class="block text-xs font-medium text-zinc-600">Target tahun</label>
                        <select name="tahun_target" id="tahun_target"
                                class="mt-1 block rounded-md border border-zinc-300 px-3 py-2 text-sm shadow-sm">
                            @foreach ([$tahunSekarang, $tahunSekarang + 1, $tahunSekarang + 2] as $pilihanTahun)
                                <option value="{{ $pilihanTahun }}">{{ $pilihanTahun }}</option>
                            @endforeach
                        </select>
                    </div>

                    <button type="submit"
                            class="min-h-11 w-full rounded-md bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-zinc-700 sm:w-auto">
                        Tandai diproses<span data-jumlah-pilih></span>
                    </button>

                    <button type="submit" formaction="{{ route('bidang.kembalikan', $filter->queryString()) }}"
                            class="min-h-11 w-full rounded-md border border-zinc-300 px-4 py-2.5 text-sm font-medium text-zinc-700 hover:bg-zinc-50 sm:w-auto">
                        Kembalikan ke antrean
                    </button>
                </div>

                @error('bidang')
                    <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>
    @endif

    {{-- Ponsel dan tablet: satu kartu per bidang. Tabel sepuluh kolom tidak terbaca di layar sempit. --}}
    <div class="space-y-3 lg:hidden">
        @forelse ($daftar as $bidang)
            <div class="rounded-lg border border-zinc-200 bg-white p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex min-w-0 items-start gap-3">
                        @if ($bolehTandai)
                            <input type="checkbox" name="bidang[]" value="{{ $bidang->id }}"
                                   class="{{ $kelasKotak }} mt-1" data-pilih
                                   aria-label="Pilih bidang {{ $bidang->nomor_urut }}">
                        @endif

                        <div class="min-w-0">
                            <a href="{{ route('bidang.show', $bidang) }}" class="block font-medium underline-offset-2 hover:underline">
                                {{ $bidang->nama_aset }}
                            </a>
                            <p class="text-xs text-zinc-500">{{ $bidang->nomor_urut }}</p>
                        </div>
                    </div>

                    <x-pm.badge :status="$bidang->status" />
                </div>

                <dl class="mt-3 space-y-1.5 text-sm">
                    <div class="flex justify-between gap-3">
                        <dt class="text-zinc-500">Instansi</dt>
                        <dd class="text-right">
                            {{ $bidang->instansi->nama }}
                            @if ($bidang->pengguna_barang)
                                <span class="block text-xs text-zinc-500">{{ $bidang->pengguna_barang }}</span>
                            @endif
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-zinc-500">Letak</dt>
                        <dd class="text-right">{{ $bidang->letak }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-zinc-500">Luas</dt>
                        <dd class="text-right tabular-nums">
                            {{ $bidang->luas_m2 !== null ? number_format((float) $bidang->luas_m2, 2, ',', '.').' m²' : '—' }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-zinc-500">Sedang menunggu</dt>
                        <dd class="text-right"><x-pm.kondisi :bidang="$bidang" /></dd>
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
                    @if ($bolehTandai)
                        <th class="w-10 px-4 py-3">
                            <input type="checkbox" class="{{ $kelasKotak }}" data-pilih-semua
                                   aria-label="Pilih seluruh bidang di halaman ini">
                        </th>
                    @endif

                    <th class="px-4 py-3">Nomor urut</th>
                    <th class="px-4 py-3">Nama aset</th>
                    <th class="px-4 py-3">Instansi</th>
                    <th class="px-4 py-3">Desa / Kecamatan</th>
                    <th class="hidden px-4 py-3 text-right xl:table-cell">Luas (m²)</th>
                    <th class="px-4 py-3">Sedang menunggu</th>
                    <th class="px-4 py-3">Penanggung jawab</th>
                    <th class="px-4 py-3 text-right">Umur</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100">
                @forelse ($daftar as $bidang)
                    <tr class="hover:bg-zinc-50">
                        @if ($bolehTandai)
                            <td class="px-4 py-3">
                                <input type="checkbox" name="bidang[]" value="{{ $bidang->id }}"
                                       class="{{ $kelasKotak }}" data-pilih
                                       aria-label="Pilih bidang {{ $bidang->nomor_urut }}">
                            </td>
                        @endif

                        <td class="px-4 py-3 font-medium">
                            <a href="{{ route('bidang.show', $bidang) }}" class="text-zinc-900 underline-offset-2 hover:underline">
                                {{ $bidang->nomor_urut }}
                            </a>
                        </td>
                        <td class="px-4 py-3">{{ $bidang->nama_aset }}</td>
                        <td class="px-4 py-3 text-zinc-600">
                            {{ $bidang->instansi->nama }}
                            @if ($bidang->pengguna_barang)
                                <span class="block text-xs text-zinc-500">{{ $bidang->pengguna_barang }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-zinc-600">{{ $bidang->letak }}</td>
                        <td class="hidden px-4 py-3 text-right tabular-nums text-zinc-600 xl:table-cell">
                            {{ $bidang->luas_m2 !== null ? number_format((float) $bidang->luas_m2, 2, ',', '.') : '—' }}
                        </td>
                        <td class="px-4 py-3"><x-pm.kondisi :bidang="$bidang" /></td>
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
                        <td colspan="{{ $bolehTandai ? 11 : 10 }}" class="px-4 py-8 text-center text-zinc-500">
                            {{ $filter->adaFilter() ? 'Tidak ada bidang yang cocok dengan penyaringan ini.' : 'Belum ada bidang.' }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($bolehTandai)
        </form>
    @endif

    <div class="mt-4">
        {{ $daftar->links() }}
    </div>
@endsection

@push('skrip')
    <script>
        (function () {
            const form = document.querySelector('[data-aksi-massal]');

            if (! form) {
                return;
            }

            const kotak = () => form.querySelectorAll('input[data-pilih]');
            const semuaHasil = form.querySelector('[data-semua-hasil]');
            const penghitung = form.querySelector('[data-jumlah-pilih]');
            const total = Number(form.dataset.total || 0);

            // Bidang yang sama punya kotak centang dua kali — satu di kartu
            // ponsel, satu di tabel desktop — jadi yang dihitung nilainya,
            // bukan jumlah kotaknya.
            const terpilih = () => new Set(
                [...kotak()].filter((k) => k.checked).map((k) => k.value)
            );

            const perbarui = () => {
                const jumlah = semuaHasil.checked ? total : terpilih().size;
                penghitung.textContent = jumlah > 0 ? ' (' + jumlah + ')' : '';
            };

            form.querySelectorAll('[data-pilih-semua]').forEach((induk) => {
                induk.addEventListener('change', () => {
                    kotak().forEach((k) => { k.checked = induk.checked; });
                    perbarui();
                });
            });

            kotak().forEach((k) => k.addEventListener('change', perbarui));
            semuaHasil.addEventListener('change', perbarui);

            form.addEventListener('submit', (peristiwa) => {
                if (! semuaHasil.checked) {
                    return;
                }

                const aksi = peristiwa.submitter && peristiwa.submitter.hasAttribute('formaction')
                    ? 'dikembalikan ke antrean'
                    : 'ditandai diproses';

                if (! window.confirm('Seluruh ' + total + ' bidang hasil penyaringan akan ' + aksi + '. Lanjutkan?')) {
                    peristiwa.preventDefault();
                }
            });

            perbarui();
        })();
    </script>
@endpush

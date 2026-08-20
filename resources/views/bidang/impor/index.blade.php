@extends('layouts.pemantauan', ['judul' => 'Impor Bidang'])

@section('isi')
    <div class="mb-4">
        <a href="{{ route('bidang.index') }}" class="inline-flex items-center gap-1 text-sm text-zinc-500 hover:text-zinc-900">
            <svg class="size-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
            </svg>
            Daftar Bidang
        </a>
        <h1 class="mt-1 text-lg font-semibold sm:text-xl">Impor Data Bidang</h1>
        <p class="mt-1 text-sm text-zinc-500">
            Menambah banyak bidang sekaligus dari berkas Excel. Berkas diperiksa lebih dulu —
            tidak ada data yang tersimpan sebelum Anda menekan konfirmasi.
        </p>
    </div>

    {{-- Penanda langkah. Di ponsel ditumpuk supaya tidak menggulir mendatar. --}}
    <ol class="mb-5 grid gap-2 text-sm sm:grid-cols-3">
        @foreach ([
            ['1', 'Unduh template', 'Berkas kosong dengan judul kolom yang benar.', true],
            ['2', 'Unggah berkas', 'Isi lalu unggah untuk diperiksa.', true],
            ['3', 'Konfirmasi', 'Simpan setelah hasil periksa bersih.', false],
        ] as [$nomor, $judul, $bantu, $sekarang])
            <li class="flex items-start gap-3 rounded-lg border p-3 {{ $sekarang ? 'border-zinc-300 bg-white' : 'border-zinc-200 bg-zinc-50' }}">
                <span class="flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold {{ $sekarang ? 'bg-zinc-900 text-white' : 'bg-zinc-200 text-zinc-500' }}">
                    {{ $nomor }}
                </span>
                <span class="min-w-0">
                    <span class="block font-medium {{ $sekarang ? 'text-zinc-900' : 'text-zinc-500' }}">{{ $judul }}</span>
                    <span class="block text-xs text-zinc-500">{{ $bantu }}</span>
                </span>
            </li>
        @endforeach
    </ol>

    <div class="grid gap-4 lg:grid-cols-5">
        {{-- Langkah 1 --}}
        <section class="rounded-lg border border-zinc-200 bg-white p-4 sm:p-5 lg:col-span-2">
            <h2 class="text-sm font-semibold">Langkah 1 — Unduh template</h2>
            <p class="mt-1 text-sm text-zinc-600">
                Template sudah berisi judul kolom yang dikenali sistem, kolom tanggal
                yang berformat tanggal, dan satu lembar petunjuk berisi arti tiap kolom
                beserta daftar nama instansi yang boleh dipakai.
            </p>

            <a href="{{ route('bidang.impor.template') }}"
               class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-md border border-zinc-300 px-4 py-2.5 text-sm font-medium text-zinc-700 hover:bg-zinc-50 sm:w-auto">
                <svg class="size-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0 4-4m-4 4-4-4M4.5 19.5h15" />
                </svg>
                Unduh template Excel
            </a>

            <p class="mt-4 border-t border-zinc-100 pt-4 text-xs text-zinc-500">
                Sudah punya data di berkas lain? Berkas hasil <span class="font-medium">Export Excel</span>
                dari halaman daftar bidang juga bisa disunting lalu diunggah kembali di sini —
                judul kolomnya sama, dan kolom tambahannya diabaikan.
            </p>
        </section>

        {{-- Langkah 2 --}}
        <section class="rounded-lg border border-zinc-200 bg-white p-4 sm:p-5 lg:col-span-3">
            <h2 class="text-sm font-semibold">Langkah 2 — Unggah berkas</h2>

            <form method="POST" action="{{ route('bidang.impor.pratinjau') }}" enctype="multipart/form-data" class="mt-3">
                @csrf

                <label for="berkas"
                       data-jatuhan
                       class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed px-4 py-8 text-center transition-colors {{ $errors->has('berkas') ? 'border-red-300 bg-red-50' : 'border-zinc-300 hover:border-zinc-400 hover:bg-zinc-50' }}">
                    <svg class="size-8 text-zinc-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V6m0 0 3.75 3.75M12 6 8.25 9.75M3 16.5v1.875A2.625 2.625 0 0 0 5.625 21h12.75A2.625 2.625 0 0 0 21 18.375V16.5" />
                    </svg>

                    <span data-nama-berkas class="text-sm font-medium text-zinc-700">
                        Seret berkas ke sini, atau ketuk untuk memilih
                    </span>
                    <span class="text-xs text-zinc-500">.xlsx, .xls, atau .csv — maksimal 5 MB dan {{ number_format($maksimalBaris, 0, ',', '.') }} baris</span>
                </label>

                {{-- sr-only, bukan hidden: tetap dapat dijangkau papan ketik. --}}
                <input type="file" name="berkas" id="berkas" required
                       accept=".xlsx,.xls,.csv"
                       class="sr-only">

                @error('berkas')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror

                <label class="mt-4 flex items-start gap-3 rounded-md border border-zinc-200 p-3">
                    <input type="checkbox" name="perbarui" value="1" @checked(old('perbarui'))
                           class="mt-0.5 size-4 shrink-0 rounded border-zinc-300 text-zinc-900 focus:ring-zinc-500">
                    <span class="text-sm">
                        <span class="font-medium">Perbarui bidang yang nomor urutnya sudah ada</span>
                        <span class="mt-0.5 block text-xs text-zinc-500">
                            Tanpa ini, nomor urut yang sudah terdaftar dianggap galat. Dengan ini,
                            seluruh isi baris menimpa data lama — termasuk mengosongkan tanggal
                            tahap yang selnya Anda biarkan kosong.
                        </span>
                    </span>
                </label>

                <button type="submit"
                        class="mt-4 w-full rounded-md bg-zinc-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-zinc-700 sm:w-auto">
                    Periksa berkas
                </button>
            </form>
        </section>
    </div>

    {{-- Ketentuan kolom. Dilipat supaya halaman tidak panjang bagi yang sudah hafal. --}}
    <details class="group mt-4 rounded-lg border border-zinc-200 bg-white">
        {{-- Petunjuk buka-tutup berupa ikon, bukan kalimat: di 375px dua
             potong teks bersebelahan sama-sama terpecah jadi dua baris. --}}
        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3 text-sm font-semibold marker:content-none sm:px-5">
            <span>
                Ketentuan kolom
                <span class="font-normal text-zinc-500">({{ count($kolom) }} kolom)</span>
            </span>
            <svg class="size-5 shrink-0 text-zinc-400 transition-transform group-open:rotate-180"
                 fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
            </svg>
        </summary>

        <div class="border-t border-zinc-100 px-4 py-4 sm:px-5">
            {{-- Ponsel: kartu. Tabel tiga kolom berisi kalimat panjang tidak terbaca di layar sempit. --}}
            <ul class="space-y-3 md:hidden">
                @foreach ($kolom as $satu)
                    <li class="rounded-md border border-zinc-200 p-3">
                        <div class="flex items-start justify-between gap-2">
                            <span class="text-sm font-medium">{{ $satu->judul }}</span>
                            <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium {{ $satu->wajib ? 'bg-zinc-900 text-white' : 'bg-zinc-100 text-zinc-600' }}">
                                {{ $satu->wajib ? 'Wajib' : 'Opsional' }}
                            </span>
                        </div>
                        <p class="mt-1 text-xs text-zinc-600">{{ $satu->petunjuk }}</p>
                    </li>
                @endforeach
            </ul>

            <table class="hidden w-full text-sm md:table">
                <thead class="text-left text-xs uppercase tracking-wide text-zinc-500">
                    <tr>
                        <th class="w-56 pb-2 pe-4 font-medium">Kolom</th>
                        <th class="w-24 pb-2 pe-4 font-medium">Wajib</th>
                        <th class="pb-2 font-medium">Penjelasan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 align-top">
                    @foreach ($kolom as $satu)
                        <tr>
                            <td class="py-2 pe-4 font-medium">{{ $satu->judul }}</td>
                            <td class="py-2 pe-4">
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $satu->wajib ? 'bg-zinc-900 text-white' : 'bg-zinc-100 text-zinc-600' }}">
                                    {{ $satu->wajib ? 'Wajib' : 'Opsional' }}
                                </span>
                            </td>
                            <td class="py-2 text-zinc-600">{{ $satu->petunjuk }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <p class="mt-4 border-t border-zinc-100 pt-3 text-xs text-zinc-500">
                Kolom <span class="font-medium">Status</span>, <span class="font-medium">Tahap aktif</span>,
                <span class="font-medium">Umur</span>, dan <span class="font-medium">Progres</span> tidak ada di sini
                karena nilainya dihitung sendiri oleh sistem dari tanggal tahap yang Anda isi.
                Kendala juga tidak diimpor — catat lewat halaman detail bidang.
            </p>
        </div>
    </details>
@endsection

@push('skrip')
    <script>
        // Seret-lepas berkas. Sengaja tanpa framework, sejalan dengan menu
        // ponsel di layouts/pemantauan: hanya memindahkan berkas ke input asli
        // supaya pengiriman form tetap berjalan biasa.
        (function () {
            var jatuhan = document.querySelector('[data-jatuhan]');
            var masukan = document.getElementById('berkas');
            var namaBerkas = document.querySelector('[data-nama-berkas]');

            if (! jatuhan || ! masukan || ! namaBerkas) {
                return;
            }

            var kelasSorot = ['border-zinc-900', 'bg-zinc-50'];

            function sorot(nyala) {
                kelasSorot.forEach(function (kelas) {
                    jatuhan.classList.toggle(kelas, nyala);
                });
            }

            function tampilkanNama() {
                var berkas = masukan.files && masukan.files[0];

                if (! berkas) {
                    return;
                }

                var ukuran = (berkas.size / 1024 / 1024).toFixed(2).replace('.', ',');

                namaBerkas.textContent = berkas.name + ' (' + ukuran + ' MB)';
                namaBerkas.classList.add('text-zinc-900');
            }

            ['dragenter', 'dragover'].forEach(function (peristiwa) {
                jatuhan.addEventListener(peristiwa, function (e) {
                    e.preventDefault();
                    sorot(true);
                });
            });

            ['dragleave', 'drop'].forEach(function (peristiwa) {
                jatuhan.addEventListener(peristiwa, function (e) {
                    e.preventDefault();
                    sorot(false);
                });
            });

            jatuhan.addEventListener('drop', function (e) {
                if (! e.dataTransfer || ! e.dataTransfer.files.length) {
                    return;
                }

                masukan.files = e.dataTransfer.files;
                tampilkanNama();
            });

            masukan.addEventListener('change', tampilkanNama);
        })();
    </script>
@endpush

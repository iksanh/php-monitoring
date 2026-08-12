{{--
    Layout halaman masuk dan pemulihan kata sandi.

    Sengaja memakai bahasa visual yang sama dengan `layouts/pemantauan`: latar
    zinc-100, kartu putih bergaris zinc-200, aksen zinc-900. Halaman pertama
    yang dilihat pengguna harus sudah terasa satu sistem dengan isi aplikasi,
    bukan halaman bawaan starter kit yang gelap dan berbahasa Inggris.

    Blade + Tailwind murni, tanpa komponen Flux maupun Livewire.

    Responsif: satu kolom di ponsel (panel kiri menyusut jadi kepala kartu),
    dua kolom mulai 1024px.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ ($judul ?? null) ? $judul.' — '.config('app.name') : config('app.name') }}</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('kepala')
</head>
<body class="min-h-svh overflow-x-hidden bg-zinc-100 text-zinc-900 antialiased">
    <div class="mx-auto flex min-h-svh max-w-5xl flex-col justify-center px-4 py-8 sm:px-6 sm:py-10">
        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm lg:grid lg:grid-cols-[1.05fr_1fr]">

            {{-- Panel identitas. Isi tahapan dibaca dari config, bukan ditulis
                 ulang di sini. --}}
            <aside class="bg-zinc-900 px-5 py-6 text-white sm:px-8 sm:py-8">
                <p class="text-xs font-medium uppercase tracking-widest text-zinc-400">
                    Kantor Pertanahan
                </p>
                <h1 class="mt-2 text-lg font-semibold leading-snug sm:text-xl">
                    Pemantauan Sertipikasi Hak Pakai
                </h1>
                <p class="mt-2 text-sm leading-relaxed text-zinc-400">
                    Papan pemantauan capaian sertipikasi aset instansi pemerintah.
                </p>

                @php
                    $tahapan = config('tahapan');
                @endphp

                <div class="mt-7 hidden lg:block">
                    <p class="text-xs font-medium uppercase tracking-wide text-zinc-500">
                        {{ count($tahapan) }} tahap yang dipantau
                    </p>

                    <ol class="mt-3 space-y-2">
                        @foreach ($tahapan as $urutan => $tahap)
                            <li class="flex items-start gap-3">
                                <span class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-zinc-800 text-[11px] font-semibold tabular-nums text-zinc-300">
                                    {{ $urutan + 1 }}
                                </span>
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-medium text-zinc-100">{{ $tahap['label'] }}</span>
                                    <span class="block truncate text-xs text-zinc-500">{{ $tahap['unit'] }}</span>
                                </span>
                            </li>
                        @endforeach
                    </ol>
                </div>

                {{-- Versi ringkas untuk ponsel dan tablet: daftar tahap terlalu
                     tinggi kalau ikut ditampilkan di atas form. --}}
                <p class="mt-4 text-xs text-zinc-500 lg:hidden">
                    Memantau {{ count($tahapan) }} tahap, dari
                    {{ \Illuminate\Support\Str::lower($tahapan[0]['label']) }}
                    sampai {{ \Illuminate\Support\Str::lower(end($tahapan)['label']) }}.
                </p>
            </aside>

            <div class="px-5 py-6 sm:px-8 sm:py-8">
                @yield('isi')
            </div>
        </div>

        <p class="mt-4 text-center text-xs text-zinc-500">
            {{ config('app.name') }} &middot; akun dibuat oleh admin aplikasi
        </p>
    </div>

    @stack('skrip')
</body>
</html>

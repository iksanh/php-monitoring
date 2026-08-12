{{--
    Layout halaman pemantauan: Blade + Tailwind murni, tanpa komponen Flux
    maupun Livewire. Halaman auth dan settings bawaan starter kit tetap
    memakai layout lamanya sendiri.

    Responsif: menu utama menjadi panel lipat di bawah 1024px, dan seluruh
    isi halaman memakai lebar penuh dengan padding yang mengecil di ponsel.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ ($judul ?? null) ? $judul.' — '.config('app.name') : config('app.name') }}</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen overflow-x-hidden bg-zinc-100 text-zinc-900 antialiased">
    @php
        $pengguna = auth()->user();
        $adminSaja = $pengguna?->berperan(\App\Enums\Peran::Admin) ?? false;
        $menu = [
            ['nama' => 'Dashboard', 'route' => 'dashboard', 'aktif' => 'dashboard', 'tampil' => true],
            ['nama' => 'Bidang', 'route' => 'bidang.index', 'aktif' => 'bidang.*', 'tampil' => true],
            ['nama' => 'Instansi', 'route' => 'instansi.index', 'aktif' => 'instansi.*', 'tampil' => $adminSaja],
            ['nama' => 'Jenis', 'route' => 'jenis-instansi.index', 'aktif' => 'jenis-instansi.*', 'tampil' => $adminSaja],
            ['nama' => 'Pengguna', 'route' => 'pengguna.index', 'aktif' => 'pengguna.*', 'tampil' => $adminSaja],
        ];
    @endphp

    <header class="sticky top-0 z-20 border-b border-zinc-200 bg-white">
        <div class="mx-auto max-w-7xl px-4 sm:px-6">
            <div class="flex h-14 items-center justify-between gap-3">
                <a href="{{ route('dashboard') }}" class="truncate text-sm font-semibold tracking-tight">
                    <span class="sm:hidden">Pemantauan Hak Pakai</span>
                    <span class="hidden sm:inline">Pemantauan Sertipikasi Hak Pakai</span>
                </a>

                {{-- Menu mendatar mulai tablet ke atas. --}}
                <nav class="hidden items-center gap-1 lg:flex">
                    @foreach ($menu as $item)
                        @continue(! $item['tampil'])
                        <a href="{{ route($item['route']) }}"
                           class="rounded-md px-3 py-1.5 text-sm font-medium {{ request()->routeIs($item['aktif']) ? 'bg-zinc-900 text-white' : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900' }}">
                            {{ $item['nama'] }}
                        </a>
                    @endforeach
                </nav>

                <div class="hidden items-center gap-3 text-sm lg:flex">
                    <a href="{{ route('profile.edit') }}" class="max-w-56 text-right leading-tight hover:opacity-70" title="Ubah profil dan kata sandi">
                        <div class="truncate font-medium">{{ $pengguna?->name }}</div>
                        <div class="truncate text-xs text-zinc-500">
                            {{ $pengguna?->role->label() }}{{ $pengguna?->instansi ? ' · '.$pengguna->instansi->nama : '' }}
                        </div>
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="rounded-md border border-zinc-300 px-3 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-50">
                            Keluar
                        </button>
                    </form>
                </div>

                <button type="button"
                        data-tombol-menu
                        aria-expanded="false"
                        aria-controls="menu-ponsel"
                        class="-me-2 inline-flex size-11 items-center justify-center rounded-md text-zinc-700 hover:bg-zinc-100 lg:hidden">
                    <span class="sr-only">Buka menu</span>
                    <svg class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" />
                    </svg>
                </button>
            </div>

            {{-- Panel menu ponsel. --}}
            <div id="menu-ponsel" hidden class="border-t border-zinc-100 py-3 lg:hidden">
                <nav class="flex flex-col gap-1">
                    @foreach ($menu as $item)
                        @continue(! $item['tampil'])
                        <a href="{{ route($item['route']) }}"
                           class="rounded-md px-3 py-2.5 text-sm font-medium {{ request()->routeIs($item['aktif']) ? 'bg-zinc-900 text-white' : 'text-zinc-700 hover:bg-zinc-100' }}">
                            {{ $item['nama'] }}
                        </a>
                    @endforeach
                </nav>

                <div class="mt-3 flex items-center justify-between gap-3 border-t border-zinc-100 pt-3">
                    <a href="{{ route('profile.edit') }}" class="min-w-0 leading-tight">
                        <div class="truncate text-sm font-medium">{{ $pengguna?->name }}</div>
                        <div class="truncate text-xs text-zinc-500">
                            {{ $pengguna?->role->label() }}{{ $pengguna?->instansi ? ' · '.$pengguna->instansi->nama : '' }}
                        </div>
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="rounded-md border border-zinc-300 px-3 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-50">
                            Keluar
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-7xl px-4 py-5 sm:px-6 sm:py-6">
        @include('partials.pesan')

        @yield('isi')
    </main>

    <script>
        // Panel menu ponsel. Sengaja tanpa framework — hanya buka/tutup.
        (function () {
            var tombol = document.querySelector('[data-tombol-menu]');
            var panel = document.getElementById('menu-ponsel');

            if (! tombol || ! panel) {
                return;
            }

            tombol.addEventListener('click', function () {
                var terbuka = ! panel.hidden;

                panel.hidden = terbuka;
                tombol.setAttribute('aria-expanded', String(! terbuka));
            });
        })();
    </script>

    @stack('skrip')
</body>
</html>

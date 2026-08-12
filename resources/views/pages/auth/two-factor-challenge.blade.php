@extends('layouts.masuk', ['judul' => 'Verifikasi dua langkah'])

@php
    // Kalau kode pemulihan yang barusan salah, panel pemulihan yang dibuka
    // lebih dulu supaya pengguna tidak perlu menekan tautan tukar lagi.
    $pakaiPemulihan = $errors->has('recovery_code');
@endphp

@section('isi')
    <div class="flex h-full flex-col justify-center">
        <form method="POST" action="{{ route('two-factor.login.store') }}" class="flex flex-col gap-4">
            @csrf

            {{-- Panel kode aplikasi autentikator. --}}
            <div data-panel="kode" @if ($pakaiPemulihan) hidden @endif>
                <h2 class="text-lg font-semibold sm:text-xl">Kode autentikasi</h2>
                <p class="mt-1 text-sm text-zinc-500">
                    Masukkan enam digit kode dari aplikasi autentikator Anda.
                </p>

                <label for="code" class="mt-6 block text-sm font-medium text-zinc-700">Kode enam digit</label>
                <input type="text"
                       name="code"
                       id="code"
                       inputmode="numeric"
                       autocomplete="one-time-code"
                       maxlength="6"
                       pattern="[0-9]*"
                       placeholder="000000"
                       @disabled($pakaiPemulihan)
                       @if (! $pakaiPemulihan) autofocus @endif
                       class="mt-1 block w-full rounded-md border px-3 py-2 text-center text-lg font-semibold tracking-[0.4em] tabular-nums shadow-sm focus:border-zinc-500 focus:ring-zinc-500 {{ $errors->has('code') ? 'border-red-400' : 'border-zinc-300' }}">

                @error('code')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Panel kode pemulihan. --}}
            <div data-panel="pemulihan" @unless ($pakaiPemulihan) hidden @endunless>
                <h2 class="text-lg font-semibold sm:text-xl">Kode pemulihan</h2>
                <p class="mt-1 text-sm text-zinc-500">
                    Masukkan salah satu kode pemulihan darurat yang Anda simpan.
                </p>

                <label for="recovery_code" class="mt-6 block text-sm font-medium text-zinc-700">Kode pemulihan</label>
                <input type="text"
                       name="recovery_code"
                       id="recovery_code"
                       autocomplete="one-time-code"
                       placeholder="xxxxxxxx-xxxxxxxx"
                       @disabled(! $pakaiPemulihan)
                       @if ($pakaiPemulihan) autofocus @endif
                       class="mt-1 block w-full rounded-md border px-3 py-2 text-sm shadow-sm focus:border-zinc-500 focus:ring-zinc-500 {{ $errors->has('recovery_code') ? 'border-red-400' : 'border-zinc-300' }}">

                @error('recovery_code')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <x-pm.tombol-utama class="mt-1">Lanjutkan</x-pm.tombol-utama>
        </form>

        <p class="mt-5 text-center text-sm text-zinc-500">
            <button type="button" data-ganti-metode
                    class="font-medium text-zinc-700 underline underline-offset-2 hover:text-zinc-900">
                {{ $pakaiPemulihan ? 'Pakai kode aplikasi autentikator' : 'Pakai kode pemulihan' }}
            </button>
        </p>

        <p class="mt-6 border-t border-zinc-100 pt-4 text-center text-xs text-zinc-500">
            <a href="{{ route('login') }}" class="font-medium text-zinc-700 underline underline-offset-2 hover:text-zinc-900">Kembali ke halaman masuk</a>
        </p>
    </div>
@endsection

@push('skrip')
    <script>
        // Tukar antara kode autentikator dan kode pemulihan. Isian panel yang
        // tersembunyi dinonaktifkan supaya tidak ikut terkirim.
        (function () {
            var tombol = document.querySelector('[data-ganti-metode]');
            var panel = {
                kode: document.querySelector('[data-panel="kode"]'),
                pemulihan: document.querySelector('[data-panel="pemulihan"]'),
            };

            if (! tombol || ! panel.kode || ! panel.pemulihan) {
                return;
            }

            tombol.addEventListener('click', function () {
                var kePemulihan = panel.pemulihan.hidden;

                atur(panel.pemulihan, kePemulihan);
                atur(panel.kode, ! kePemulihan);

                tombol.textContent = kePemulihan
                    ? 'Pakai kode aplikasi autentikator'
                    : 'Pakai kode pemulihan';
            });

            function atur(wadah, tampil) {
                var isian = wadah.querySelector('input');

                wadah.hidden = ! tampil;
                isian.disabled = ! tampil;
                isian.value = '';

                if (tampil) {
                    isian.focus();
                }
            }
        })();
    </script>
@endpush

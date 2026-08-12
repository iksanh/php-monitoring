@extends('layouts.masuk', ['judul' => 'Konfirmasi kata sandi'])

@section('isi')
    <div class="flex h-full flex-col justify-center">
        <h2 class="text-lg font-semibold sm:text-xl">Konfirmasi kata sandi</h2>
        <p class="mt-1 text-sm text-zinc-500">
            Bagian ini terkunci demi keamanan. Masukkan kembali kata sandi Anda untuk melanjutkan.
        </p>

        <x-pm.status :status="session('status')" />

        <form method="POST" action="{{ route('password.confirm.store') }}" class="mt-6 flex flex-col gap-4">
            @csrf

            <x-pm.sandi label="Kata sandi" name="password"
                        placeholder="Kata sandi" required autofocus autocomplete="current-password" />

            <x-pm.tombol-utama class="mt-1" data-test="confirm-password-button">
                Konfirmasi
            </x-pm.tombol-utama>
        </form>

        <x-pm.passkey options-route="passkey.confirm-options"
                      submit-route="passkey.confirm"
                      label="Konfirmasi dengan passkey"
                      label-proses="Mengonfirmasi..."
                      pemisah="atau" />

        <p class="mt-6 border-t border-zinc-100 pt-4 text-center text-xs text-zinc-500">
            <a href="{{ route('dashboard') }}" class="font-medium text-zinc-700 underline underline-offset-2 hover:text-zinc-900">Kembali ke dashboard</a>
        </p>
    </div>
@endsection

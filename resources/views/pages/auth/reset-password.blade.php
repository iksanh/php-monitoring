@extends('layouts.masuk', ['judul' => 'Setel ulang kata sandi'])

@section('isi')
    <div class="flex h-full flex-col justify-center">
        <h2 class="text-lg font-semibold sm:text-xl">Setel ulang kata sandi</h2>
        <p class="mt-1 text-sm text-zinc-500">
            Masukkan kata sandi baru untuk akun ini.
        </p>

        <x-pm.status :status="session('status')" />

        <form method="POST" action="{{ route('password.update') }}" class="mt-6 flex flex-col gap-4">
            @csrf
            <input type="hidden" name="token" value="{{ request()->route('token') }}">

            <x-pm.input label="Email" name="email" type="email" :value="request('email')"
                        required autocomplete="email" inputmode="email" />

            <x-pm.sandi label="Kata sandi baru" name="password"
                        placeholder="Kata sandi baru" required autocomplete="new-password"
                        passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}" />

            <x-pm.sandi label="Ulangi kata sandi baru" name="password_confirmation"
                        placeholder="Ulangi kata sandi baru" required autocomplete="new-password"
                        passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}" />

            <x-pm.tombol-utama class="mt-1" data-test="reset-password-button">
                Simpan kata sandi baru
            </x-pm.tombol-utama>
        </form>

        <p class="mt-6 border-t border-zinc-100 pt-4 text-center text-xs text-zinc-500">
            <a href="{{ route('login') }}" class="font-medium text-zinc-700 underline underline-offset-2 hover:text-zinc-900">Kembali ke halaman masuk</a>
        </p>
    </div>
@endsection

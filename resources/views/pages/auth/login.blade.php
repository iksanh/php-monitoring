@extends('layouts.masuk', ['judul' => 'Masuk'])

@section('isi')
    <div class="flex h-full flex-col justify-center">
        <h2 class="text-lg font-semibold sm:text-xl">Masuk</h2>
        <p class="mt-1 text-sm text-zinc-500">
            Gunakan email dan kata sandi yang diberikan admin aplikasi.
        </p>

        <x-pm.status :status="session('status')" />

        <form method="POST" action="{{ route('login.store') }}" class="mt-6 flex flex-col gap-4">
            @csrf

            <x-pm.input label="Email" name="email" type="email"
                        placeholder="nama@instansi.go.id"
                        required autofocus autocomplete="email" inputmode="email" />

            <x-pm.sandi label="Kata sandi" name="password"
                        placeholder="Kata sandi" required autocomplete="current-password">
                @if (Route::has('password.request'))
                    <x-slot:aksi>
                        <a href="{{ route('password.request') }}" class="text-xs font-medium text-zinc-600 underline underline-offset-2 hover:text-zinc-900">
                            Lupa kata sandi?
                        </a>
                    </x-slot:aksi>
                @endif
            </x-pm.sandi>

            <label class="flex items-center gap-2 text-sm text-zinc-700">
                <input type="checkbox"
                       name="remember"
                       value="1"
                       @checked(old('remember'))
                       class="size-4 rounded border-zinc-300 text-zinc-900 focus:ring-zinc-500">
                Ingat saya di perangkat ini
            </label>

            <x-pm.tombol-utama class="mt-1" data-test="login-button">Masuk</x-pm.tombol-utama>
        </form>

        <x-pm.passkey />

        {{-- Registrasi mandiri ditutup: akun dibuat admin lewat menu Pengguna. --}}
        <p class="mt-6 border-t border-zinc-100 pt-4 text-center text-xs text-zinc-500">
            Belum punya akun? Hubungi admin aplikasi.
        </p>
    </div>
@endsection

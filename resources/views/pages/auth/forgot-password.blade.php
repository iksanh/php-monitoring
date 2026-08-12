@extends('layouts.masuk', ['judul' => 'Lupa kata sandi'])

@section('isi')
    <div class="flex h-full flex-col justify-center">
        <h2 class="text-lg font-semibold sm:text-xl">Lupa kata sandi</h2>
        <p class="mt-1 text-sm text-zinc-500">
            Masukkan email akun Anda. Tautan penyetelan ulang kata sandi akan dikirim ke email tersebut.
        </p>

        <x-pm.status :status="session('status')" />

        <form method="POST" action="{{ route('password.email') }}" class="mt-6 flex flex-col gap-4">
            @csrf

            <x-pm.input label="Email" name="email" type="email"
                        placeholder="nama@instansi.go.id"
                        required autofocus autocomplete="email" inputmode="email" />

            <x-pm.tombol-utama class="mt-1" data-test="email-password-reset-link-button">
                Kirim tautan setel ulang
            </x-pm.tombol-utama>
        </form>

        <p class="mt-6 border-t border-zinc-100 pt-4 text-center text-xs text-zinc-500">
            Ingat kata sandinya?
            <a href="{{ route('login') }}" class="font-medium text-zinc-700 underline underline-offset-2 hover:text-zinc-900">Kembali ke halaman masuk</a>
        </p>
    </div>
@endsection

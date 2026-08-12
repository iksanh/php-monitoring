@extends('layouts.masuk', ['judul' => 'Verifikasi email'])

@section('isi')
    <div class="flex h-full flex-col justify-center">
        <h2 class="text-lg font-semibold sm:text-xl">Verifikasi email</h2>
        <p class="mt-1 text-sm text-zinc-500">
            Kami mengirim tautan verifikasi{{ auth()->user()?->email ? ' ke '.auth()->user()->email : '' }}.
            Buka email itu dan klik tautannya untuk mengaktifkan akun.
        </p>

        @if (session('status') === 'verification-link-sent')
            <x-pm.status status="Tautan verifikasi baru sudah dikirim ke email Anda." />
        @endif

        <form method="POST" action="{{ route('verification.send') }}" class="mt-6">
            @csrf
            <x-pm.tombol-utama>Kirim ulang tautan verifikasi</x-pm.tombol-utama>
        </form>

        <form method="POST" action="{{ route('logout') }}" class="mt-6 border-t border-zinc-100 pt-4 text-center">
            @csrf
            <button type="submit" data-test="logout-button"
                    class="text-xs font-medium text-zinc-600 underline underline-offset-2 hover:text-zinc-900">
                Keluar dan masuk dengan akun lain
            </button>
        </form>
    </div>
@endsection

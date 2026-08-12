@extends('layouts.pemantauan', ['judul' => 'Ubah Kendala'])

@section('isi')
    <div class="mb-4">
        <h1 class="text-lg font-semibold sm:text-xl">Ubah Kendala</h1>
        <p class="text-sm text-zinc-500">
            Bidang <a href="{{ route('bidang.show', $bidang) }}" class="underline">{{ $bidang->nomor_urut }}</a>
            — {{ $bidang->nama_aset }}
        </p>
    </div>

    <form method="POST" action="{{ route('kendala.update', $kendala) }}"
          class="grid gap-4 rounded-lg border border-zinc-200 bg-white p-4 sm:grid-cols-2 sm:p-5">
        @csrf
        @method('PUT')

        <div class="sm:col-span-2">
            <x-pm.textarea label="Uraian kendala" name="uraian" :value="$kendala->uraian" wajib />
        </div>

        <x-pm.input label="Tanggal catat" name="tanggal_catat" type="date"
                    :value="\App\Support\Tanggal::input($kendala->tanggal_catat)" wajib />

        <x-pm.input label="Tanggal selesai" name="tanggal_selesai" type="date"
                    :value="\App\Support\Tanggal::input($kendala->tanggal_selesai)"
                    bantu="Kosongkan bila kendala masih terbuka." />

        <x-pm.input label="Dicatat oleh" name="dicatat_oleh" :value="$kendala->dicatat_oleh" wajib />

        <div class="flex flex-wrap items-center gap-3 sm:col-span-2">
            <button type="submit" class="w-full rounded-md bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-zinc-700 sm:w-auto">
                Simpan
            </button>
            <a href="{{ route('bidang.show', $bidang) }}" class="text-sm font-medium text-zinc-600 hover:text-zinc-900">Batal</a>
        </div>
    </form>
@endsection

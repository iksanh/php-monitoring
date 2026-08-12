@extends('layouts.pemantauan', ['judul' => 'Ubah Bidang '.$bidang->nomor_urut])

@section('isi')
    <div class="mb-4">
        <h1 class="text-lg font-semibold sm:text-xl">Ubah Bidang {{ $bidang->nomor_urut }}</h1>
        <p class="text-sm text-zinc-500">{{ $bidang->nama_aset }}</p>
    </div>

    @include('bidang._form', ['aksi' => route('bidang.update', $bidang)])
@endsection

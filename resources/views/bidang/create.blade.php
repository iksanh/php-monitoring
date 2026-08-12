@extends('layouts.pemantauan', ['judul' => 'Tambah Bidang'])

@section('isi')
    <div class="mb-4">
        <h1 class="text-lg font-semibold sm:text-xl">Tambah Bidang</h1>
        <p class="text-sm text-zinc-500">Lengkapi identitas aset, lalu isi tanggal tahap yang sudah selesai.</p>
    </div>

    @include('bidang._form', ['aksi' => route('bidang.store')])
@endsection

@extends('layouts.pemantauan', ['judul' => 'Ubah Pengguna'])

@section('isi')
    <h1 class="mb-4 text-lg font-semibold sm:text-xl">Ubah Pengguna</h1>

    @include('pengguna._form', ['aksi' => route('pengguna.update', $pengguna)])
@endsection

@extends('layouts.pemantauan', ['judul' => 'Tambah Pengguna'])

@section('isi')
    <h1 class="mb-4 text-lg font-semibold sm:text-xl">Tambah Pengguna</h1>

    @include('pengguna._form', ['aksi' => route('pengguna.store')])
@endsection

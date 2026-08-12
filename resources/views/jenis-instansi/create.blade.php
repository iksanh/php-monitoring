@extends('layouts.pemantauan', ['judul' => 'Tambah Jenis Instansi'])

@section('isi')
    <h1 class="mb-4 text-lg font-semibold sm:text-xl">Tambah Jenis Instansi</h1>

    @include('jenis-instansi._form', ['aksi' => route('jenis-instansi.store')])
@endsection

@extends('layouts.pemantauan', ['judul' => 'Tambah Instansi'])

@section('isi')
    <h1 class="mb-4 text-lg font-semibold sm:text-xl">Tambah Instansi</h1>

    @include('instansi._form', ['aksi' => route('instansi.store')])
@endsection

@extends('layouts.pemantauan', ['judul' => 'Ubah Jenis Instansi'])

@section('isi')
    <h1 class="mb-4 text-lg font-semibold sm:text-xl">Ubah Jenis Instansi</h1>

    @include('jenis-instansi._form', ['aksi' => route('jenis-instansi.update', $jenis)])
@endsection

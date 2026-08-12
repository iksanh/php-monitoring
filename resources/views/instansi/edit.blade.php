@extends('layouts.pemantauan', ['judul' => 'Ubah Instansi'])

@section('isi')
    <h1 class="mb-4 text-lg font-semibold sm:text-xl">Ubah Instansi</h1>

    @include('instansi._form', ['aksi' => route('instansi.update', $instansi)])
@endsection

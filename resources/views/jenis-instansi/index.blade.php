@extends('layouts.pemantauan', ['judul' => 'Jenis Instansi'])

@section('isi')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold sm:text-xl">Jenis Instansi</h1>
            <p class="text-sm text-zinc-500">
                Dipakai sebagai pilihan jenis pada master instansi.
            </p>
        </div>

        <a href="{{ route('jenis-instansi.create') }}"
           class="w-full rounded-md bg-zinc-900 px-4 py-2.5 text-center text-sm font-medium text-white hover:bg-zinc-700 sm:w-auto">
            Tambah jenis
        </a>
    </div>

    <div class="overflow-x-auto rounded-lg border border-zinc-200 bg-white">
        <table class="min-w-full divide-y divide-zinc-200 text-sm">
            <thead class="bg-zinc-50 text-left text-xs uppercase tracking-wide text-zinc-500">
                <tr>
                    <th class="px-3 py-3 sm:px-4">Nama</th>
                    <th class="hidden px-3 py-3 sm:table-cell sm:px-4">Kode</th>
                    <th class="hidden px-3 py-3 text-right sm:table-cell sm:px-4">Instansi</th>
                    <th class="px-3 py-3 sm:px-4">Aktif</th>
                    <th class="px-3 py-3 sm:px-4"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100">
                @forelse ($daftar as $jenis)
                    <tr class="hover:bg-zinc-50">
                        <td class="px-3 py-3 font-medium sm:px-4">
                            {{ $jenis->nama }}
                            <span class="block text-xs font-normal text-zinc-500 sm:hidden">
                                {{ $jenis->kode }} · {{ $jenis->instansi_count }} instansi
                            </span>
                        </td>
                        <td class="hidden px-3 py-3 font-mono text-xs text-zinc-500 sm:table-cell sm:px-4">{{ $jenis->kode }}</td>
                        <td class="hidden px-3 py-3 text-right tabular-nums sm:table-cell sm:px-4">{{ $jenis->instansi_count }}</td>
                        <td class="px-3 py-3 sm:px-4">
                            <span class="inline-flex whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium {{ $jenis->aktif ? 'bg-emerald-100 text-emerald-800' : 'bg-zinc-200 text-zinc-600' }}">
                                {{ $jenis->aktif ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        <td class="px-3 py-3 sm:px-4">
                            <div class="flex items-center justify-end gap-3">
                                <a href="{{ route('jenis-instansi.edit', $jenis) }}" class="text-sm font-medium text-zinc-700 hover:text-zinc-900">Ubah</a>

                                @can('delete', $jenis)
                                    <form method="POST" action="{{ route('jenis-instansi.destroy', $jenis) }}"
                                          onsubmit="return confirm('Hapus jenis {{ $jenis->nama }}?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-sm font-medium text-red-700 hover:text-red-900">Hapus</button>
                                    </form>
                                @else
                                    <span class="text-sm text-zinc-300" title="Masih dipakai instansi">Hapus</span>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-zinc-500">Belum ada jenis instansi.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $daftar->links() }}</div>
@endsection

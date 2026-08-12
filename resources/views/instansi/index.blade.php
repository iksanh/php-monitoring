@extends('layouts.pemantauan', ['judul' => 'Master Instansi'])

@section('isi')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold sm:text-xl">Master Instansi</h1>
            <p class="text-sm text-zinc-500">Instansi pemilik aset dan asal pengguna.</p>
        </div>

        <a href="{{ route('instansi.create') }}"
           class="w-full rounded-md bg-zinc-900 px-4 py-2.5 text-center text-sm font-medium text-white hover:bg-zinc-700 sm:w-auto">
            Tambah instansi
        </a>
    </div>

    <div class="overflow-x-auto rounded-lg border border-zinc-200 bg-white">
        <table class="min-w-full divide-y divide-zinc-200 text-sm">
            <thead class="bg-zinc-50 text-left text-xs uppercase tracking-wide text-zinc-500">
                <tr>
                    <th class="px-3 py-3 sm:px-4">Nama</th>
                    <th class="hidden px-3 py-3 sm:px-4 sm:table-cell">Jenis</th>
                    <th class="hidden px-3 py-3 sm:px-4 text-right md:table-cell">Bidang</th>
                    <th class="hidden px-3 py-3 sm:px-4 text-right md:table-cell">Pengguna</th>
                    <th class="px-3 py-3 sm:px-4">Aktif</th>
                    <th class="px-3 py-3 sm:px-4"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100">
                @forelse ($daftar as $instansi)
                    <tr class="hover:bg-zinc-50">
                        <td class="px-3 py-3 sm:px-4 font-medium">
                            {{ $instansi->nama }}
                            <span class="block text-xs font-normal text-zinc-500 sm:hidden">
                                {{ $instansi->jenis->nama }} · {{ $instansi->bidang_count }} bidang
                            </span>
                        </td>
                        <td class="hidden px-3 py-3 sm:px-4 text-zinc-600 sm:table-cell">{{ $instansi->jenis->nama }}</td>
                        <td class="hidden px-3 py-3 sm:px-4 text-right tabular-nums md:table-cell">{{ $instansi->bidang_count }}</td>
                        <td class="hidden px-3 py-3 sm:px-4 text-right tabular-nums md:table-cell">{{ $instansi->pengguna_count }}</td>
                        <td class="px-3 py-3 sm:px-4">
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $instansi->aktif ? 'bg-emerald-100 text-emerald-800' : 'bg-zinc-200 text-zinc-600' }}">
                                {{ $instansi->aktif ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        <td class="px-3 py-3 sm:px-4">
                            <div class="flex items-center justify-end gap-3">
                                <a href="{{ route('instansi.edit', $instansi) }}" class="text-sm font-medium text-zinc-700 hover:text-zinc-900">Ubah</a>

                                @can('delete', $instansi)
                                    <form method="POST" action="{{ route('instansi.destroy', $instansi) }}"
                                          onsubmit="return confirm('Hapus instansi {{ $instansi->nama }}?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-sm font-medium text-red-700 hover:text-red-900">Hapus</button>
                                    </form>
                                @else
                                    <span class="text-sm text-zinc-300" title="Masih dipakai bidang atau pengguna">Hapus</span>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-zinc-500">Belum ada instansi.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $daftar->links() }}</div>
@endsection

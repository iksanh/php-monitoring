@extends('layouts.pemantauan', ['judul' => 'Manajemen Pengguna'])

@section('isi')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold sm:text-xl">Manajemen Pengguna</h1>
            <p class="text-sm text-zinc-500">
                Registrasi mandiri ditutup — akun hanya dibuat di halaman ini.
            </p>
        </div>

        <a href="{{ route('pengguna.create') }}"
           class="w-full rounded-md bg-zinc-900 px-4 py-2.5 text-center text-sm font-medium text-white hover:bg-zinc-700 sm:w-auto">
            Tambah pengguna
        </a>
    </div>

    <div class="overflow-x-auto rounded-lg border border-zinc-200 bg-white">
        <table class="min-w-full divide-y divide-zinc-200 text-sm">
            <thead class="bg-zinc-50 text-left text-xs uppercase tracking-wide text-zinc-500">
                <tr>
                    <th class="px-3 py-3 sm:px-4">Nama</th>
                    <th class="hidden px-3 py-3 sm:px-4 sm:table-cell">Surel</th>
                    <th class="hidden px-3 py-3 sm:table-cell sm:px-4">Peran</th>
                    <th class="hidden px-3 py-3 sm:px-4 md:table-cell">Instansi</th>
                    <th class="px-3 py-3 sm:px-4"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100">
                @foreach ($daftar as $pengguna)
                    <tr class="hover:bg-zinc-50">
                        <td class="px-3 py-3 sm:px-4 font-medium">
                            {{ $pengguna->name }}
                            @if ($pengguna->is(auth()->user()))
                                <span class="ms-1 text-xs text-zinc-400">(Anda)</span>
                            @endif
                            <span class="block truncate text-xs font-normal text-zinc-500 sm:hidden">{{ $pengguna->role->label() }} · {{ $pengguna->email }}</span>
                        </td>
                        <td class="hidden px-3 py-3 sm:px-4 text-zinc-600 sm:table-cell">{{ $pengguna->email }}</td>
                        <td class="hidden px-3 py-3 sm:table-cell sm:px-4">{{ $pengguna->role->label() }}</td>
                        <td class="hidden px-3 py-3 sm:px-4 text-zinc-600 md:table-cell">{{ $pengguna->instansi?->nama ?? '—' }}</td>
                        <td class="px-3 py-3 sm:px-4">
                            <div class="flex items-center justify-end gap-3">
                                <a href="{{ route('pengguna.edit', $pengguna) }}" class="text-sm font-medium text-zinc-700 hover:text-zinc-900">Ubah</a>

                                @can('delete', $pengguna)
                                    <form method="POST" action="{{ route('pengguna.destroy', $pengguna) }}"
                                          onsubmit="return confirm('Hapus pengguna {{ $pengguna->name }}?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-sm font-medium text-red-700 hover:text-red-900">Hapus</button>
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $daftar->links() }}</div>
@endsection

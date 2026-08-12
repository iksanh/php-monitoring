<form method="POST" action="{{ $aksi }}" class="grid max-w-2xl gap-4 rounded-lg border border-zinc-200 bg-white p-4 sm:p-5">
    @csrf
    @if ($jenis->exists)
        @method('PUT')
    @endif

    <x-pm.input label="Nama jenis" name="nama" :value="$jenis->nama" wajib
                bantu="Misal: Pemerintah Daerah, Kantor Pertanahan, BUMD, Kementerian." />

    @if ($jenis->exists)
        <div>
            <p class="block text-sm font-medium text-zinc-700">Kode</p>
            <p class="mt-1 font-mono text-sm text-zinc-500">{{ $jenis->kode }}</p>
            <p class="mt-1 text-xs text-zinc-500">
                Dibuat otomatis saat jenis ditambahkan dan tidak berubah meski namanya diganti.
            </p>
        </div>
    @endif

    <label class="flex items-center gap-2 text-sm">
        <input type="hidden" name="aktif" value="0">
        <input type="checkbox" name="aktif" value="1" class="rounded border-zinc-300"
               @checked((bool) old('aktif', $jenis->aktif ?? true))>
        Aktif — tampil sebagai pilihan saat menambah instansi
    </label>

    <div class="flex flex-wrap items-center gap-3">
        <button type="submit" class="w-full rounded-md bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-zinc-700 sm:w-auto">
            Simpan
        </button>
        <a href="{{ route('jenis-instansi.index') }}" class="text-sm font-medium text-zinc-600 hover:text-zinc-900">Batal</a>
    </div>
</form>

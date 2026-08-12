<form method="POST" action="{{ $aksi }}" class="grid max-w-2xl gap-4 rounded-lg border border-zinc-200 bg-white p-4 sm:p-5">
    @csrf
    @if ($instansi->exists)
        @method('PUT')
    @endif

    <x-pm.input label="Nama instansi" name="nama" :value="$instansi->nama" wajib />

    <x-pm.select label="Jenis" name="jenis_instansi_id" :value="$instansi->jenis_instansi_id"
                 kosong="— pilih jenis —" :pilihan="$jenis" wajib
                 bantu="Jenis dikelola di menu Jenis Instansi." />

    <label class="flex items-center gap-2 text-sm">
        <input type="hidden" name="aktif" value="0">
        <input type="checkbox" name="aktif" value="1" class="rounded border-zinc-300"
               @checked((bool) old('aktif', $instansi->aktif ?? true))>
        Instansi aktif
    </label>

    <div class="flex flex-wrap items-center gap-3">
        <button type="submit" class="w-full rounded-md bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-zinc-700 sm:w-auto">
            Simpan
        </button>
        <a href="{{ route('instansi.index') }}" class="text-sm font-medium text-zinc-600 hover:text-zinc-900">Batal</a>
    </div>
</form>

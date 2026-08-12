<form method="POST" action="{{ $aksi }}" class="grid max-w-2xl gap-4 rounded-lg border border-zinc-200 bg-white p-4 sm:p-5">
    @csrf
    @if ($pengguna->exists)
        @method('PUT')
    @endif

    <x-pm.input label="Nama" name="name" :value="$pengguna->name" wajib />
    <x-pm.input label="Surel" name="email" type="email" :value="$pengguna->email" wajib />

    <x-pm.select label="Peran" name="role" :value="$pengguna->role?->value" :pilihan="$peran" wajib
                 bantu="Operator adalah satu-satunya peran yang memutakhirkan data pemantauan. Pemantau hanya membaca, tetapi membaca seluruh data lintas instansi." />

    <x-pm.select label="Instansi" name="instansi_id" :value="$pengguna->instansi_id"
                 kosong="— tanpa instansi —" :pilihan="$instansi->pluck('nama', 'id')->all()" />

    <x-pm.input label="Kata sandi" name="password" type="password" :wajib="! $pengguna->exists"
                :bantu="$pengguna->exists ? 'Kosongkan bila kata sandi tidak diganti.' : null" />

    <x-pm.input label="Ulangi kata sandi" name="password_confirmation" type="password" :wajib="! $pengguna->exists" />

    <div class="flex flex-wrap items-center gap-3">
        <button type="submit" class="w-full rounded-md bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-zinc-700 sm:w-auto">
            Simpan
        </button>
        <a href="{{ route('pengguna.index') }}" class="text-sm font-medium text-zinc-600 hover:text-zinc-900">Batal</a>
    </div>
</form>

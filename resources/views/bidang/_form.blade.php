{{--
    Satu halaman form. Bagian tanggal tahap dirender dengan loop atas
    config('tahapan') — tidak ada nama tahap yang ditulis di sini.
--}}
<form method="POST" action="{{ $aksi }}" class="space-y-6">
    @csrf
    @if ($bidang->exists)
        @method('PUT')
    @endif

    <section class="rounded-lg border border-zinc-200 bg-white p-4 sm:p-5">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500">Identitas aset</h2>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <x-pm.input label="Nomor urut" name="nomor_urut" :value="$bidang->nomor_urut" wajib />
            <x-pm.input label="Nama aset" name="nama_aset" :value="$bidang->nama_aset" wajib />

            <x-pm.select label="Instansi pemilik aset"
                         name="instansi_id"
                         :value="$bidang->instansi_id"
                         kosong="— pilih instansi —"
                         :pilihan="$instansi->pluck('nama', 'id')->all()"
                         wajib />

            <x-pm.input label="Penggunaan" name="penggunaan" :value="$bidang->penggunaan"
                        bantu="Misal: kantor, sekolah, puskesmas." />

            <x-pm.input label="Desa" name="desa" :value="$bidang->desa" wajib />
            <x-pm.input label="Kecamatan" name="kecamatan" :value="$bidang->kecamatan" wajib />

            <x-pm.input label="Luas (m²)" name="luas_m2" type="number" step="0.01" min="0" :value="$bidang->luas_m2" />
            <x-pm.input label="Nomor berkas KKP" name="nomor_berkas_kkp" :value="$bidang->nomor_berkas_kkp" />

            <x-pm.input label="Tahun target" name="tahun_target" type="number" min="2000" max="2100"
                        :value="$bidang->tahun_target ?? now()->year" wajib />

            <x-pm.select label="Status" name="status" :value="$bidang->status?->value"
                         :pilihan="\App\Enums\StatusBidang::pilihan()" wajib />
        </div>

        <div class="mt-4">
            <x-pm.textarea label="Keterangan" name="keterangan" :value="$bidang->keterangan" />
        </div>
    </section>

    <section class="rounded-lg border border-zinc-200 bg-white p-4 sm:p-5">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500">Tanggal tahapan</h2>
        <p class="mt-1 text-sm text-zinc-500">
            Isi tanggal tahap yang sudah selesai. Urutan tidak dipaksakan — tahap boleh dilewati.
        </p>

        <div class="mt-4 space-y-4">
            @foreach ($tahapan as $tahap)
                @php
                    $statusSekarang = $tahap->kolomStatus
                        ? old($tahap->kolomStatus, $bidang->{$tahap->kolomStatus}?->value ?? \App\Enums\StatusTahap::Berlaku->value)
                        : null;
                    $dinonaktifkan = $statusSekarang === \App\Enums\StatusTahap::TidakBerlaku->value;
                @endphp

                <div class="grid gap-4 border-t border-zinc-100 pt-4 sm:grid-cols-2 sm:items-start">
                    <x-pm.input :label="$tahap->urutan.'. '.$tahap->label"
                                :name="$tahap->kolom"
                                type="date"
                                :value="\App\Support\Tanggal::input($bidang->{$tahap->kolom})"
                                :bantu="$tahap->unit.' · '.$tahap->dokumen"
                                :disabled="$dinonaktifkan"
                                data-tanggal-tahap="{{ $tahap->kolom }}" />

                    @if ($tahap->kolomStatus)
                        <x-pm.select label="Berlaku untuk bidang ini?"
                                     :name="$tahap->kolomStatus"
                                     :value="$statusSekarang"
                                     :pilihan="\App\Enums\StatusTahap::pilihan()"
                                     bantu="Tahap kondisional. Bila tidak berlaku, tahap ini tidak dihitung."
                                     data-status-tahap="{{ $tahap->kolom }}" />
                    @endif
                </div>
            @endforeach
        </div>
    </section>

    <div class="flex flex-wrap items-center gap-3">
        <button type="submit" class="w-full rounded-md bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-zinc-700 sm:w-auto">
            Simpan
        </button>
        <a href="{{ $bidang->exists ? route('bidang.show', $bidang) : route('bidang.index') }}"
           class="text-sm font-medium text-zinc-600 hover:text-zinc-900">
            Batal
        </a>
    </div>
</form>

<script>
    // Tahap kondisional yang dinyatakan tidak berlaku: input tanggalnya dimatikan.
    document.querySelectorAll('[data-status-tahap]').forEach(function (pilihan) {
        var kolom = pilihan.dataset.statusTahap;
        var tanggal = document.querySelector('[data-tanggal-tahap="' + kolom + '"]');

        if (! tanggal) {
            return;
        }

        pilihan.addEventListener('change', function () {
            tanggal.disabled = pilihan.value === @json(\App\Enums\StatusTahap::TidakBerlaku->value);
        });
    });
</script>

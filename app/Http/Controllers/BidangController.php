<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exports\BidangExport;
use App\Http\Requests\BidangRequest;
use App\Http\Requests\ProsesBidangRequest;
use App\Models\Bidang;
use App\Models\Instansi;
use App\Support\Filter\FilterBidang;
use App\Support\Tahapan;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BidangController extends Controller
{
    /**
     * Banyak model yang dimuat sekaligus pada aksi massal.
     */
    private const PER_POTONG = 500;

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Bidang::class);

        $filter = FilterBidang::dariRequest($request);

        $daftar = $filter
            ->terapkan(Bidang::query()->with(['instansi', 'kendalaAktif']))
            ->orderBy('nomor_urut')
            ->paginate(25)
            ->withQueryString();

        return view('bidang.index', [
            'daftar' => $daftar,
            'filter' => $filter,
            'instansi' => Instansi::query()->orderBy('nama')->get(),
            'tahapan' => Tahapan::semua(),
            'tahunTersedia' => $this->tahunTersedia(),
            'penggunaBarangTersedia' => $this->penggunaBarangTersedia(),
        ]);
    }

    /**
     * Export daftar bidang sesuai filter yang sedang aktif.
     */
    public function export(Request $request): BinaryFileResponse
    {
        Gate::authorize('viewAny', Bidang::class);

        $berkas = 'bidang-hak-pakai-'.now()->format('Ymd-His').'.xlsx';

        return Excel::download(new BidangExport(FilterBidang::dariRequest($request)), $berkas);
    }

    /**
     * Menandai bidang terpilih sedang diproses Kantor Pertanahan.
     *
     * Daftar aset pemda memuat ribuan bidang; yang dikerjakan hanya sebagian
     * yang dipilih di sini. Tanpa pemilihan itu seluruh aset yang belum
     * bersertipikat terbaca sebagai pekerjaan berjalan.
     */
    public function proses(ProsesBidangRequest $request): RedirectResponse
    {
        Gate::authorize('create', Bidang::class);

        $tahun = $request->tahunTarget();
        $ditandai = 0;

        $this->atasBidangTerpilih($request, function (Bidang $bidang) use ($tahun, &$ditandai): void {
            $bidang->tgl_diproses ??= today();
            $bidang->tahun_target = $tahun;

            if ($bidang->isDirty()) {
                $bidang->save();
                $ditandai++;
            }
        });

        return back()->with(
            'sukses',
            $ditandai === 0
                ? 'Tidak ada yang berubah — bidang terpilih memang sudah diproses untuk target tahun '.$tahun.'.'
                : $ditandai.' bidang ditandai sedang diproses, target tahun '.$tahun.'.'
        );
    }

    /**
     * Mengembalikan bidang terpilih ke antrean daftar aset.
     *
     * Bidang yang tanggal tahapnya sudah terisi tidak ikut: pekerjaannya sudah
     * berjalan, dan mengembalikannya berarti menyembunyikan berkas yang sedang
     * ditunggu dari dashboard.
     */
    public function kembalikan(ProsesBidangRequest $request): RedirectResponse
    {
        Gate::authorize('create', Bidang::class);

        $dikembalikan = 0;
        $ditolak = 0;

        $this->atasBidangTerpilih($request, function (Bidang $bidang) use (&$dikembalikan, &$ditolak): void {
            if ($bidang->tgl_diproses === null) {
                return;
            }

            if ($bidang->tahapAktif !== null) {
                $ditolak++;

                return;
            }

            $bidang->tgl_diproses = null;
            $bidang->save();
            $dikembalikan++;
        });

        $pesan = $dikembalikan.' bidang dikembalikan ke antrean daftar aset.';

        if ($ditolak > 0) {
            $pesan .= ' '.$ditolak.' bidang dilewati karena tanggal tahapnya sudah terisi;'
                .' hapus dulu tanggalnya bila memang salah proses.';
        }

        return back()->with('sukses', $pesan);
    }

    /**
     * Menjalankan satu aksi atas bidang sasaran: yang dicentang, atau seluruh
     * hasil penyaringan bila operator memilih begitu.
     *
     * Modelnya diambil per potongan dan disimpan lewat model — bukan satu
     * `update` massal — sebab kolom `status` ditulis BidangObserver lewat event
     * model. Relasi kendala ikut dimuat supaya status tidak memicu satu query
     * per baris. Seluruhnya dalam satu transaksi: aksi ini mengubah ribuan
     * baris sekaligus dan tidak boleh berhenti separuh jalan.
     *
     * @param  callable(Bidang): void  $aksi
     */
    private function atasBidangTerpilih(ProsesBidangRequest $request, callable $aksi): void
    {
        $query = $request->semuaHasil()
            ? FilterBidang::dariRequest($request)->terapkan(Bidang::query())
            : Bidang::query()->whereIn('id', $request->idTerpilih());

        /** @var list<int> $id */
        $id = $query->orderBy('id')->pluck('id')->all();

        DB::transaction(function () use ($id, $aksi): void {
            foreach (array_chunk($id, self::PER_POTONG) as $bagian) {
                foreach (Bidang::query()->with('kendalaAktif')->whereIn('id', $bagian)->get() as $bidang) {
                    $aksi($bidang);
                }
            }
        });
    }

    public function create(): View
    {
        Gate::authorize('create', Bidang::class);

        return view('bidang.create', [
            'bidang' => new Bidang,
            'instansi' => $this->instansi(),
            'tahapan' => Tahapan::semua(),
        ]);
    }

    public function store(BidangRequest $request): RedirectResponse
    {
        Gate::authorize('create', Bidang::class);

        $bidang = Bidang::query()->create($request->validated());

        return redirect()
            ->route('bidang.show', $bidang)
            ->with('sukses', 'Bidang '.$bidang->nomor_urut.' berhasil ditambahkan.');
    }

    public function show(Bidang $bidang): View
    {
        Gate::authorize('view', $bidang);

        $bidang->load([
            'instansi',
            'kendala' => fn ($query) => $query->orderByDesc('tanggal_catat'),
        ]);

        return view('bidang.show', [
            'bidang' => $bidang,
            'tahapan' => Tahapan::semua(),
        ]);
    }

    public function edit(Bidang $bidang): View
    {
        Gate::authorize('update', $bidang);

        return view('bidang.edit', [
            'bidang' => $bidang,
            'instansi' => $this->instansi(),
            'tahapan' => Tahapan::semua(),
        ]);
    }

    public function update(BidangRequest $request, Bidang $bidang): RedirectResponse
    {
        Gate::authorize('update', $bidang);

        $bidang->update($request->validated());

        return redirect()
            ->route('bidang.show', $bidang)
            ->with('sukses', 'Bidang '.$bidang->nomor_urut.' berhasil diperbarui.');
    }

    public function destroy(Bidang $bidang): RedirectResponse
    {
        Gate::authorize('delete', $bidang);

        $bidang->delete();

        return redirect()
            ->route('bidang.index')
            ->with('sukses', 'Bidang '.$bidang->nomor_urut.' dipindahkan ke arsip.');
    }

    /**
     * @return Collection<int, Instansi>
     */
    private function instansi(): Collection
    {
        return Instansi::query()->aktif()->orderBy('nama')->get();
    }

    /**
     * Pengguna barang yang benar-benar tercatat, untuk isi kotak filter.
     *
     * @return list<string>
     */
    private function penggunaBarangTersedia(): array
    {
        return array_values(
            Bidang::query()
                ->select('pengguna_barang')
                ->whereNotNull('pengguna_barang')
                ->distinct()
                ->orderBy('pengguna_barang')
                ->pluck('pengguna_barang')
                ->map(fn (mixed $nama): string => (string) $nama)
                ->all()
        );
    }

    /**
     * Tahun target yang benar-benar ada datanya, untuk isi kotak filter.
     *
     * @return list<int>
     */
    private function tahunTersedia(): array
    {
        return array_values(
            Bidang::query()
                ->select('tahun_target')
                ->distinct()
                ->orderByDesc('tahun_target')
                ->pluck('tahun_target')
                ->map(fn (mixed $tahun): int => (int) $tahun)
                ->all()
        );
    }
}

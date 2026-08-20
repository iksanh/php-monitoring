<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exports\TemplateImporBidang;
use App\Http\Requests\ImporBidangRequest;
use App\Imports\PembacaBidang;
use App\Models\Bidang;
use App\Support\Impor\BarisImpor;
use App\Support\Impor\HasilImpor;
use App\Support\Impor\KolomImpor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Impor bidang dari berkas Excel, tiga langkah: unduh template, unggah, lalu
 * konfirmasi setelah melihat pratinjau.
 *
 * Tidak ada satu baris pun yang ditulis sebelum langkah ketiga, dan penulisan
 * itu sendiri berlangsung dalam satu transaksi — berkas yang bergalat sebagian
 * tidak akan pernah masuk separuh-separuh.
 *
 * Berkas unggahan disimpan sementara di disk lokal, bukan di session: session
 * bawaan proyek ini file-based dan tidak cocok memuat berkas. Kepemilikan
 * berkas dijaga token acak yang hanya ada di session pengunggahnya.
 */
class ImporBidangController extends Controller
{
    private const FOLDER = 'impor-bidang';

    private const KUNCI_SESSION = 'impor_bidang';

    /**
     * Umur berkas titipan sebelum disapu, dalam jam. Penyapuannya menumpang
     * pada unggahan berikutnya — di shared hosting tidak ada scheduler yang
     * bisa diandalkan.
     */
    private const UMUR_BERKAS_JAM = 24;

    public function form(): View
    {
        Gate::authorize('create', Bidang::class);

        return view('bidang.impor.index', [
            'kolom' => KolomImpor::semua(),
            'maksimalBaris' => PembacaBidang::MAKSIMAL_BARIS,
        ]);
    }

    /**
     * Template kosong berisi baris judul yang persis dikenali pembaca.
     */
    public function template(): BinaryFileResponse
    {
        Gate::authorize('create', Bidang::class);

        return Excel::download(new TemplateImporBidang, 'template-impor-bidang.xlsx');
    }

    /**
     * Langkah kedua: berkas dibaca dan divalidasi seluruhnya, belum disimpan.
     */
    public function pratinjau(ImporBidangRequest $request): View|RedirectResponse
    {
        Gate::authorize('create', Bidang::class);

        $this->sapuBerkasLama();

        $berkas = $request->file('berkas');
        $token = (string) Str::uuid();

        $path = $berkas->storeAs(
            self::FOLDER,
            $token.'.'.mb_strtolower($berkas->getClientOriginalExtension())
        );

        if ($path === false) {
            return back()->with('gagal', 'Berkas gagal disimpan sementara di server. Coba unggah ulang.');
        }

        $hasil = (new PembacaBidang($request->bolehPerbarui()))->baca(Storage::path($path));

        if ($hasil->berkasBermasalah()) {
            Storage::delete($path);

            return back()->with('gagal', $hasil->galatBerkas);
        }

        $request->session()->put(self::KUNCI_SESSION, [
            'path' => $path,
            'nama' => $berkas->getClientOriginalName(),
            'perbarui' => $request->bolehPerbarui(),
        ]);

        return view('bidang.impor.pratinjau', [
            'hasil' => $hasil,
            'namaBerkas' => $berkas->getClientOriginalName(),
            'perbarui' => $request->bolehPerbarui(),
        ]);
    }

    /**
     * Langkah ketiga: berkas dibaca ulang lalu ditulis dalam satu transaksi.
     *
     * Sengaja dibaca ulang, bukan mengandalkan hasil pratinjau yang disimpan:
     * data bidang bisa saja berubah di antara dua langkah ini, dan validasi
     * yang menentukan haruslah yang berlaku pada saat penyimpanan.
     */
    public function simpan(Request $request): RedirectResponse
    {
        Gate::authorize('create', Bidang::class);

        /** @var array{path: string, nama: string, perbarui: bool}|null $titipan */
        $titipan = $request->session()->get(self::KUNCI_SESSION);

        if ($titipan === null || ! Storage::exists($titipan['path'])) {
            return redirect()
                ->route('bidang.impor.form')
                ->with('gagal', 'Berkas unggahan sudah tidak tersedia. Silakan unggah ulang.');
        }

        $hasil = (new PembacaBidang($titipan['perbarui']))->baca(Storage::path($titipan['path']));

        if (! $hasil->siapDisimpan()) {
            return redirect()
                ->route('bidang.impor.form')
                ->with('gagal', 'Berkas ternyata masih bermasalah saat hendak disimpan. Periksa lagi lalu unggah ulang.');
        }

        $ringkasan = $this->tulis($hasil);

        $this->bersihkan($request);

        return redirect()
            ->route('bidang.index')
            ->with('sukses', $ringkasan);
    }

    /**
     * Membatalkan pratinjau: berkas titipan dibuang tanpa menyimpan apa pun.
     */
    public function batal(Request $request): RedirectResponse
    {
        Gate::authorize('create', Bidang::class);

        $this->bersihkan($request);

        return redirect()
            ->route('bidang.impor.form')
            ->with('sukses', 'Impor dibatalkan. Tidak ada data yang tersimpan.');
    }

    /**
     * Penulisan sesungguhnya. Memakai model, bukan insert massal, karena
     * kolom `status` ditulis App\Observers\BidangObserver lewat event model.
     */
    private function tulis(HasilImpor $hasil): string
    {
        $ditambah = 0;
        $diperbarui = 0;

        DB::transaction(function () use ($hasil, &$ditambah, &$diperbarui): void {
            foreach ($hasil->barisSah() as $baris) {
                if ($baris->aksi() === BarisImpor::TAMBAH) {
                    Bidang::query()->create($baris->nilai);
                    $ditambah++;

                    continue;
                }

                Bidang::query()->findOrFail($baris->bidangId)->update($baris->nilai);
                $diperbarui++;
            }
        });

        $pesan = $ditambah.' bidang ditambahkan';

        return $diperbarui > 0
            ? $pesan.' dan '.$diperbarui.' bidang diperbarui dari berkas Excel.'
            : $pesan.' dari berkas Excel.';
    }

    private function bersihkan(Request $request): void
    {
        /** @var array{path: string}|null $titipan */
        $titipan = $request->session()->pull(self::KUNCI_SESSION);

        if ($titipan !== null) {
            Storage::delete($titipan['path']);
        }
    }

    /**
     * Berkas yang pratinjaunya ditinggalkan tanpa konfirmasi tidak pernah
     * terhapus sendiri. Disapu di sini supaya tidak menumpuk.
     */
    private function sapuBerkasLama(): void
    {
        $batas = now()->subHours(self::UMUR_BERKAS_JAM)->getTimestamp();

        foreach (Storage::files(self::FOLDER) as $berkas) {
            if (Storage::lastModified($berkas) < $batas) {
                Storage::delete($berkas);
            }
        }
    }
}

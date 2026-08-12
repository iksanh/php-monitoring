<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exports\BidangExport;
use App\Http\Requests\BidangRequest;
use App\Models\Bidang;
use App\Models\Instansi;
use App\Support\Filter\FilterBidang;
use App\Support\Tahapan;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BidangController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Bidang::class);

        $filter = FilterBidang::dariRequest($request);

        $daftar = $filter
            ->terapkan(Bidang::query()->with('instansi'))
            ->orderBy('nomor_urut')
            ->paginate(25)
            ->withQueryString();

        return view('bidang.index', [
            'daftar' => $daftar,
            'filter' => $filter,
            'instansi' => Instansi::query()->orderBy('nama')->get(),
            'tahapan' => Tahapan::semua(),
            'tahunTersedia' => $this->tahunTersedia(),
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

        $bidang->load(['instansi', 'kendala' => fn ($query) => $query->orderByDesc('tanggal_catat')]);

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

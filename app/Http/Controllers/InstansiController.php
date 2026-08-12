<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\InstansiRequest;
use App\Models\Instansi;
use App\Models\JenisInstansi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class InstansiController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Instansi::class);

        return view('instansi.index', [
            'daftar' => Instansi::query()
                ->with('jenis')->withCount(['bidang', 'pengguna'])
                ->orderBy('nama')
                ->paginate(25),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Instansi::class);

        return view('instansi.create', [
            'instansi' => new Instansi(['aktif' => true]),
            'jenis' => JenisInstansi::query()->aktif()->orderBy('nama')->pluck('nama', 'id')->all(),
        ]);
    }

    public function store(InstansiRequest $request): RedirectResponse
    {
        Gate::authorize('create', Instansi::class);

        $instansi = Instansi::query()->create($request->validated());

        return redirect()
            ->route('instansi.index')
            ->with('sukses', 'Instansi '.$instansi->nama.' ditambahkan.');
    }

    public function edit(Instansi $instansi): View
    {
        Gate::authorize('update', $instansi);

        return view('instansi.edit', [
            'instansi' => $instansi,
            'jenis' => JenisInstansi::query()->aktif()->orderBy('nama')->pluck('nama', 'id')->all(),
        ]);
    }

    public function update(InstansiRequest $request, Instansi $instansi): RedirectResponse
    {
        Gate::authorize('update', $instansi);

        $instansi->update($request->validated());

        return redirect()
            ->route('instansi.index')
            ->with('sukses', 'Instansi '.$instansi->nama.' diperbarui.');
    }

    public function destroy(Instansi $instansi): RedirectResponse
    {
        Gate::authorize('delete', $instansi);

        $nama = $instansi->nama;
        $instansi->delete();

        return redirect()
            ->route('instansi.index')
            ->with('sukses', 'Instansi '.$nama.' dihapus.');
    }
}

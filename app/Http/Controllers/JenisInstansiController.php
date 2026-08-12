<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\JenisInstansiRequest;
use App\Models\JenisInstansi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class JenisInstansiController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', JenisInstansi::class);

        return view('jenis-instansi.index', [
            'daftar' => JenisInstansi::query()
                ->withCount('instansi')
                ->orderBy('nama')
                ->paginate(25),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', JenisInstansi::class);

        return view('jenis-instansi.create', [
            'jenis' => new JenisInstansi(['aktif' => true]),
        ]);
    }

    public function store(JenisInstansiRequest $request): RedirectResponse
    {
        Gate::authorize('create', JenisInstansi::class);

        $jenis = JenisInstansi::query()->create($request->validated());

        return redirect()
            ->route('jenis-instansi.index')
            ->with('sukses', 'Jenis instansi '.$jenis->nama.' ditambahkan.');
    }

    public function edit(JenisInstansi $jenisInstansi): View
    {
        Gate::authorize('update', $jenisInstansi);

        return view('jenis-instansi.edit', ['jenis' => $jenisInstansi]);
    }

    public function update(JenisInstansiRequest $request, JenisInstansi $jenisInstansi): RedirectResponse
    {
        Gate::authorize('update', $jenisInstansi);

        $jenisInstansi->update($request->validated());

        return redirect()
            ->route('jenis-instansi.index')
            ->with('sukses', 'Jenis instansi '.$jenisInstansi->nama.' diperbarui.');
    }

    public function destroy(JenisInstansi $jenisInstansi): RedirectResponse
    {
        Gate::authorize('delete', $jenisInstansi);

        $nama = $jenisInstansi->nama;
        $jenisInstansi->delete();

        return redirect()
            ->route('jenis-instansi.index')
            ->with('sukses', 'Jenis instansi '.$nama.' dihapus.');
    }
}

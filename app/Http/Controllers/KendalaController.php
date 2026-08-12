<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\KendalaRequest;
use App\Models\Bidang;
use App\Models\Kendala;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Kendala dikelola dari halaman detail bidang, bukan halaman tersendiri.
 */
class KendalaController extends Controller
{
    public function store(KendalaRequest $request, Bidang $bidang): RedirectResponse
    {
        Gate::authorize('create', Kendala::class);

        $bidang->kendala()->create($request->validated());

        return redirect()
            ->route('bidang.show', $bidang)
            ->with('sukses', 'Kendala dicatat.');
    }

    public function edit(Kendala $kendala): View
    {
        Gate::authorize('update', $kendala);

        return view('kendala.edit', [
            'kendala' => $kendala,
            'bidang' => $kendala->bidang,
        ]);
    }

    public function update(KendalaRequest $request, Kendala $kendala): RedirectResponse
    {
        Gate::authorize('update', $kendala);

        $kendala->update($request->validated());

        return redirect()
            ->route('bidang.show', $kendala->bidang_id)
            ->with('sukses', 'Kendala diperbarui.');
    }

    public function destroy(Kendala $kendala): RedirectResponse
    {
        Gate::authorize('delete', $kendala);

        $bidangId = $kendala->bidang_id;
        $kendala->delete();

        return redirect()
            ->route('bidang.show', $bidangId)
            ->with('sukses', 'Kendala dihapus.');
    }
}

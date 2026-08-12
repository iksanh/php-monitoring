<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Peran;
use App\Http\Requests\PenggunaRequest;
use App\Models\Instansi;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class PenggunaController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', User::class);

        return view('pengguna.index', [
            'daftar' => User::query()->with('instansi')->orderBy('name')->paginate(25),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', User::class);

        return view('pengguna.create', [
            'pengguna' => new User,
            'peran' => Peran::pilihan(),
            'instansi' => $this->instansi(),
        ]);
    }

    public function store(PenggunaRequest $request): RedirectResponse
    {
        Gate::authorize('create', User::class);

        $data = $request->validated();

        $pengguna = new User;
        $pengguna->forceFill([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'instansi_id' => $data['instansi_id'] ?? null,
            'password' => Hash::make((string) $data['password']),
            // Akun dibuat admin, jadi alamat surelnya dianggap sudah sahih.
            'email_verified_at' => now(),
        ])->save();

        return redirect()
            ->route('pengguna.index')
            ->with('sukses', 'Pengguna '.$pengguna->name.' ditambahkan.');
    }

    public function edit(User $pengguna): View
    {
        Gate::authorize('update', $pengguna);

        return view('pengguna.edit', [
            'pengguna' => $pengguna,
            'peran' => Peran::pilihan(),
            'instansi' => $this->instansi(),
        ]);
    }

    public function update(PenggunaRequest $request, User $pengguna): RedirectResponse
    {
        Gate::authorize('update', $pengguna);

        $data = $request->validated();

        $atribut = [
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'instansi_id' => $data['instansi_id'] ?? null,
        ];

        if (filled($data['password'] ?? null)) {
            $atribut['password'] = Hash::make((string) $data['password']);
        }

        $pengguna->forceFill($atribut)->save();

        return redirect()
            ->route('pengguna.index')
            ->with('sukses', 'Pengguna '.$pengguna->name.' diperbarui.');
    }

    public function destroy(User $pengguna): RedirectResponse
    {
        Gate::authorize('delete', $pengguna);

        $nama = $pengguna->name;
        $pengguna->delete();

        return redirect()
            ->route('pengguna.index')
            ->with('sukses', 'Pengguna '.$nama.' dihapus.');
    }

    /**
     * @return Collection<int, Instansi>
     */
    private function instansi(): Collection
    {
        return Instansi::query()->orderBy('nama')->get();
    }
}

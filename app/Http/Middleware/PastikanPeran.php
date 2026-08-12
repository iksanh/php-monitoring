<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Peran;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware `role:` buatan sendiri — tanpa package permission pihak ketiga.
 *
 * Dipakai sebagai penjaga kasar di tingkat route, mis. `role:admin,operator`.
 * Keputusan per objek tetap urusan Policy.
 */
class PastikanPeran
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$peran): Response
    {
        $user = Auth::user();

        if ($user === null) {
            abort(403);
        }

        $diizinkan = array_map(
            static fn (string $nama): Peran => Peran::from($nama),
            $peran,
        );

        if (! $user->berperan(...$diizinkan)) {
            abort(403, 'Peran Anda tidak berhak membuka halaman ini.');
        }

        return $next($request);
    }
}

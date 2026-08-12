<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(DashboardService $dashboard): View
    {
        $tahun = (int) date('Y');
        $pemutakhiran = $dashboard->pemutakhiranTerakhir();

        return view('dashboard', [
            'tahun' => $tahun,
            'kartu' => $dashboard->kartuAngka($tahun),
            'sebaran' => $dashboard->sebaranTertahan(),
            'perPenanggungJawab' => $dashboard->tertahanPerPenanggungJawab(),
            'perKategoriKendala' => $dashboard->terkendalaPerKategori($tahun),
            'capaian' => $dashboard->capaianPerInstansi($tahun),
            'terlama' => $dashboard->bidangTerlama(),
            'pemutakhiran' => $pemutakhiran,
            'dataBasi' => $dashboard->dataBasi($pemutakhiran),
        ]);
    }
}

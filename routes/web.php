<?php

use App\Http\Controllers\BidangController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InstansiController;
use App\Http\Controllers\JenisInstansiController;
use App\Http\Controllers\KendalaController;
use App\Http\Controllers\PenggunaController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // Harus di atas route resource, kalau tidak "export" akan ditangkap
    // sebagai parameter {bidang}.
    Route::get('bidang/export', [BidangController::class, 'export'])->name('bidang.export');

    // Membaca terbuka untuk semua peran; menulis dijaga Policy di controller.
    // Parameter ditulis eksplisit: penunggalan otomatis Laravel bekerja untuk
    // bahasa Inggris dan mengacaukan kata seperti "pengguna" atau "instansi".
    Route::resource('bidang', BidangController::class)
        ->parameters(['bidang' => 'bidang']);

    Route::post('bidang/{bidang}/kendala', [KendalaController::class, 'store'])->name('kendala.store');
    Route::get('kendala/{kendala}/edit', [KendalaController::class, 'edit'])->name('kendala.edit');
    Route::put('kendala/{kendala}', [KendalaController::class, 'update'])->name('kendala.update');
    Route::delete('kendala/{kendala}', [KendalaController::class, 'destroy'])->name('kendala.destroy');

    // Master data: admin saja, dijaga dua lapis (middleware role + Policy).
    Route::middleware('role:admin')->group(function () {
        Route::resource('jenis-instansi', JenisInstansiController::class)
            ->parameters(['jenis-instansi' => 'jenisInstansi'])
            ->except('show');

        Route::resource('instansi', InstansiController::class)
            ->parameters(['instansi' => 'instansi'])
            ->except('show');

        Route::resource('pengguna', PenggunaController::class)
            ->parameters(['pengguna' => 'pengguna'])
            ->except('show');
    });
});

require __DIR__.'/settings.php';

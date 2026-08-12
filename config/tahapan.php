<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Tahapan Sertipikasi Hak Pakai
|--------------------------------------------------------------------------
|
| Acuan tunggal seluruh aplikasi. Form input, timeline detail, label grafik,
| header tabel, dan header export SEMUA membaca dari sini. Nama tahap
| kemungkinan diubah pimpinan; perubahan itu harus cukup di file ini saja.
|
| Urutan array = urutan tahap. Tiap elemen:
|
|   kolom            nama kolom tanggal di tabel `bidang`
|   label            nama tahap yang ditampilkan
|   unit             unit pelaksana
|   penanggung_jawab pihak yang memegang bola pada tahap ini
|                    (harus salah satu nilai App\Enums\PenanggungJawab)
|   dokumen          dokumen dasar penanda tahap selesai
|   sifat            wajib | kondisional
|   kolom_status     kolom pendamping untuk tahap kondisional, null bila wajib
|
| Menambah tahap: tambahkan elemen di sini DAN kolom tanggalnya lewat migrasi
| baru. Menambah pihak penanggung jawab baru: tambahkan case pada enum
| App\Enums\PenanggungJawab.
|
*/

return [

    [
        'kolom' => 'tgl_permohonan',
        'label' => 'Permohonan',
        'unit' => 'Loket/PTSP',
        'penanggung_jawab' => 'kantah',
        'dokumen' => 'Tanda terima DI 301',
        'sifat' => 'wajib',
        'kolom_status' => null,
    ],

    [
        'kolom' => 'tgl_pengukuran',
        'label' => 'Pengukuran',
        'unit' => 'Seksi Survei',
        'penanggung_jawab' => 'kantah',
        'dokumen' => 'Peta Bidang / Surat Ukur',
        'sifat' => 'wajib',
        'kolom_status' => null,
    ],

    [
        'kolom' => 'tgl_pengumuman',
        'label' => 'Pengumuman 30 Hari',
        'unit' => 'Seksi PHP',
        'penanggung_jawab' => 'kantah',
        'dokumen' => 'BA Pengesahan Pengumuman',
        'sifat' => 'kondisional',
        'kolom_status' => 'pengumuman_status',
    ],

    [
        'kolom' => 'tgl_pemeriksaan',
        'label' => 'Pemeriksaan Tanah',
        'unit' => 'Tim Peneliti',
        'penanggung_jawab' => 'kantah',
        'dokumen' => 'BA Penelitian Tanah',
        'sifat' => 'wajib',
        'kolom_status' => null,
    ],

    [
        'kolom' => 'tgl_sk',
        'label' => 'Penetapan SK',
        'unit' => 'Kepala Kantor',
        'penanggung_jawab' => 'kantah',
        'dokumen' => 'SK Pemberian Hak Pakai',
        'sifat' => 'wajib',
        'kolom_status' => null,
    ],

    [
        'kolom' => 'tgl_kewajiban',
        'label' => 'Pemenuhan Kewajiban',
        'unit' => 'Pemohon',
        'penanggung_jawab' => 'pemohon',
        'dokumen' => 'Bukti bayar / ket. nihil',
        'sifat' => 'kondisional',
        'kolom_status' => 'kewajiban_status',
    ],

    [
        'kolom' => 'tgl_sertipikat',
        'label' => 'Penerbitan Sertipikat',
        'unit' => 'Kepala Kantor',
        'penanggung_jawab' => 'kantah',
        'dokumen' => 'Sertipikat elektronik',
        'sifat' => 'wajib',
        'kolom_status' => null,
    ],

    [
        'kolom' => 'tgl_serah_terima',
        'label' => 'Serah Terima & Aset',
        'unit' => 'Pemohon',
        'penanggung_jawab' => 'pemohon',
        'dokumen' => 'BA Serah Terima / KIB',
        'sifat' => 'wajib',
        'kolom_status' => null,
    ],

];

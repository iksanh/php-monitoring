<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Tahapan Sertipikasi Hak Pakai
|--------------------------------------------------------------------------
|
| Acuan tunggal seluruh aplikasi. Form input, timeline detail, label grafik,
| header tabel, dan header export SEMUA membaca dari sini. Nama dan urutan
| tahap kemungkinan masih berubah; perubahan itu harus cukup di file ini saja.
|
| Urutan array = urutan tahap. Seluruh tahap wajib — tidak ada tahap
| kondisional. Tiap elemen:
|
|   kolom            nama kolom tanggal di tabel `bidang`
|   label            nama tahap dari sisi apa yang sudah SELESAI
|   label_menunggu   nama tahap dari sisi apa yang sedang DITUNGGU
|   unit             unit pelaksana
|   penanggung_jawab pihak yang memegang bola pada tahap ini
|                    (harus salah satu nilai App\Enums\PenanggungJawab)
|   dokumen          dokumen dasar penanda tahap selesai
|
| Dua label, dua kegunaan: `label` dipakai di timeline detail, label input
| form, dan header export; `label_menunggu` dipakai di mana pun aplikasi
| menyebut kondisi berjalan sebuah bidang — kolom tahap daftar bidang, tabel
| bidang terlama, dan label sumbu grafik bidang tertahan. Aturan lengkapnya di
| docs/spec.md bagian 6.
|
| PKKPR mendahului pengukuran. Berkas yang masuk tanpa PKKPR terbaca sendiri
| dari `tgl_permohonan` terisi sementara `tgl_pkkpr` kosong — tidak perlu
| ditandai khusus dan bukan kendala.
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
        'label_menunggu' => 'Berkas Masuk',
        'unit' => 'Loket/PTSP',
        'penanggung_jawab' => 'kantah',
        'dokumen' => 'Tanda terima berkas (DI 301)',
    ],

    [
        'kolom' => 'tgl_pkkpr',
        'label' => 'PKKPR',
        'label_menunggu' => 'Daftar PKKPR',
        'unit' => 'Pemohon (OSS)',
        'penanggung_jawab' => 'pemohon',
        'dokumen' => 'Persetujuan KKPR',
    ],

    [
        'kolom' => 'tgl_pengukuran',
        'label' => 'Pengukuran',
        'label_menunggu' => 'Pengukuran',
        'unit' => 'Seksi Survei & Pemetaan',
        'penanggung_jawab' => 'kantah',
        'dokumen' => 'Peta Bidang Tanah / Surat Ukur',
    ],

    [
        'kolom' => 'tgl_peta_analisis',
        'label' => 'Peta Analisis',
        'label_menunggu' => 'Peta Analisis',
        'unit' => 'Seksi Survei & Pemetaan',
        'penanggung_jawab' => 'kantah',
        'dokumen' => 'Peta Analisis Bidang Tanah',
    ],

    [
        'kolom' => 'tgl_panitia_a',
        'label' => 'Pemeriksaan Panitia A',
        'label_menunggu' => 'Menunggu Jadwal Panitia A',
        'unit' => 'Panitia Pemeriksaan Tanah A',
        'penanggung_jawab' => 'kantah',
        'dokumen' => 'Risalah Pemeriksaan Tanah',
    ],

    [
        'kolom' => 'tgl_sk',
        'label' => 'Penetapan SK',
        'label_menunggu' => 'Proses SK',
        'unit' => 'Kepala Kantor',
        'penanggung_jawab' => 'kantah',
        'dokumen' => 'SK Pemberian Hak Pakai',
    ],

    [
        'kolom' => 'tgl_sertipikat',
        'label' => 'Penerbitan Sertipikat',
        'label_menunggu' => 'Proses Sertipikat',
        'unit' => 'Kepala Kantor',
        'penanggung_jawab' => 'kantah',
        'dokumen' => 'Sertipikat elektronik',
    ],

    [
        'kolom' => 'tgl_serah_terima',
        'label' => 'Serah Terima',
        'label_menunggu' => 'Siap Diserahkan',
        'unit' => 'Loket/PTSP',
        'penanggung_jawab' => 'pemohon',
        'dokumen' => 'BA Serah Terima / KIB',
    ],

];

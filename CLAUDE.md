# Aturan Proyek — Dashboard Pemantauan Sertipikasi Hak Pakai

Spesifikasi lengkap ada di `docs/spec.md`. Baca file itu sebelum mengerjakan
tugas apa pun yang menyentuh domain, skema, atau halaman.

## Lingkungan

- Laravel 13, PHP 8.2+, MySQL 8
- Target deploy: Hostinger shared hosting
- Tanpa Redis, tanpa supervisor, tanpa queue worker daemon
  → `QUEUE_CONNECTION=sync`, `CACHE_STORE=file`
- Blade + Tailwind CSS + Chart.js. Tanpa Livewire, Inertia, atau SPA.
- Aset frontend harus jalan tanpa proses build di server produksi.

## Aturan keras

- Nama, urutan, unit, dan kedua label tahap SELALU dibaca dari
  `config('tahapan')`. Nol hardcode nama tahap di Blade, controller, service,
  atau migrasi. Seluruh tahap wajib — tidak ada lagi tahap kondisional.
- Tiap tahap punya dua label. `label` menyebut tahap yang sudah selesai
  (timeline detail, label input form, header export); `label_menunggu`
  menyebut kondisi berjalan (kolom tahap daftar bidang, tabel bidang terlama,
  label sumbu grafik). Aturan lengkapnya di `docs/spec.md` bagian 6.
- Kolom `status` pada bidang adalah nilai turunan, bukan isian operator.
  Satu-satunya penulisnya `App\Observers\BidangObserver`; jangan
  mengembalikannya ke form, ke `$fillable`, atau ke FormRequest. Karena itu
  `DatabaseSeeder` tidak boleh memakai trait `WithoutModelEvents` — event
  model mati berarti seluruh data contoh berstatus `proses`.
- Query agregasi dashboard ditulis di `DashboardService`, bukan di controller.
- Sebaran bidang per tahap dihitung dengan SATU query `SUM(CASE WHEN ...)`,
  bukan delapan query terpisah.
- Tahap aktif tidak pernah disimpan sebagai kolom — selalu diturunkan lewat
  accessor pada model `Bidang`.
- Tanpa package permission pihak ketiga. Gunakan Gate/Policy bawaan Laravel.
- Satu-satunya package tambahan yang diizinkan: `maatwebsite/excel`.
  Penambahan dependency lain wajib konfirmasi saya dulu.
- Locale aplikasi `id`. Format tanggal tampilan `d M Y` dengan nama bulan
  Indonesia.
- Hindari N+1. Eager load relasi pada halaman daftar dan dashboard.
- Semua halaman wajib responsif di ponsel, tablet, dan desktop. Halaman tidak
  boleh menggulir mendatar, dan tabel lebar disajikan ulang sebagai kartu di
  layar sempit. Ketentuan lengkapnya di `docs/spec.md` bagian 8; periksa tiap
  halaman baru pada lebar 375px, 768px, dan 1440px sebelum menyatakan selesai.

## Larangan

- Jangan jalankan `migrate:fresh` atau `db:wipe` tanpa saya minta eksplisit.
- Jangan ubah `.env` tanpa memberi tahu saya.
- Jangan membangun workflow engine, validasi urutan tahap, approval, notifikasi,
  atau upload dokumen. Aplikasi ini papan pemantauan manual, bukan pengolah
  berkas. Alasannya ada di `docs/spec.md` bagian 1.

## Cara kerja

- Kerjakan satu tahap (A, B, C, atau D) per sesi. Berhenti di batas tahap dan
  tunggu konfirmasi saya sebelum lanjut.
- Sebelum menulis kode, ringkas dulu rencana file yang akan dibuat beserta
  alasan singkat tiap file.
- Setelah selesai satu tahap, sebutkan perintah verifikasi yang harus saya
  jalankan (migrate, seed, test).
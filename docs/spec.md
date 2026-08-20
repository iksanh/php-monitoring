# Spesifikasi — Aplikasi Dashboard Pemantauan Sertipikasi Tanah Hak Pakai Instansi Pemerintah

## 1. Konteks dan batas tegas

Aplikasi web dashboard pemantauan progres sertipikasi tanah Hak Pakai milik
instansi pemerintah, dipakai bersama oleh tiga pihak: Pemerintah Daerah,
Kantor Pertanahan, dan Kejaksaan.

Proses pendaftaran tanah yang sesungguhnya berjalan di aplikasi KKP milik
Kementerian ATR/BPN dan TIDAK dapat diintegrasikan. Aplikasi ini adalah papan
pemantauan manual: satu operator Kantor Pertanahan memutakhirkan tanggal
tahapan secara berkala, pihak lain memantau.

Konsekuensinya, JANGAN membangun:

- workflow engine atau state machine
- validasi urutan tahap — operator harus bebas mengisi tanggal mana pun,
  termasuk melewati tahap
- sistem approval, disposisi, atau notifikasi
- upload dan manajemen dokumen
- integrasi API apa pun

Yang dibangun hanya: input data, simpan, tampilkan, agregasi, export.

## 2. TAHAPAN — acuan utama seluruh aplikasi

Ada 8 tahap, seluruhnya wajib. Tidak ada tahap kondisional. Setiap tahap
diwakili satu kolom tanggal di tabel `bidang`. Tanggal terisi berarti tahap
selesai; kosong berarti belum.

| # | kolom            | label                 | label_menunggu            | unit                       | penanggung_jawab | dokumen dasar                 |
|---|------------------|-----------------------|---------------------------|----------------------------|------------------|-------------------------------|
| 1 | tgl_permohonan   | Permohonan            | Berkas Masuk              | Loket/PTSP                 | kantah           | Tanda terima berkas (DI 301)  |
| 2 | tgl_pkkpr        | PKKPR                 | Daftar PKKPR              | Pemohon (OSS)              | pemohon          | Persetujuan KKPR              |
| 3 | tgl_pengukuran   | Pengukuran            | Pengukuran                | Seksi Survei & Pemetaan    | kantah           | Peta Bidang Tanah / Surat Ukur|
| 4 | tgl_peta_analisis| Peta Analisis         | Peta Analisis             | Seksi Survei & Pemetaan    | kantah           | Peta Analisis Bidang Tanah    |
| 5 | tgl_panitia_a    | Pemeriksaan Panitia A | Menunggu Jadwal Panitia A | Panitia Pemeriksaan Tanah A| kantah           | Risalah Pemeriksaan Tanah     |
| 6 | tgl_sk           | Penetapan SK          | Proses SK                 | Kepala Kantor              | kantah           | SK Pemberian Hak Pakai        |
| 7 | tgl_sertipikat   | Penerbitan Sertipikat | Proses Sertipikat         | Kepala Kantor              | kantah           | Sertipikat elektronik         |
| 8 | tgl_serah_terima | Serah Terima          | Siap Diserahkan           | Loket/PTSP                 | pemohon          | BA Serah Terima / KIB         |

Definisikan di `config/tahapan.php` sebagai array berurutan. Tiap elemen berisi
key: `kolom`, `label`, `label_menunggu`, `unit`, `penanggung_jawab`, `dokumen`.

**Dua jenis label, dua kegunaan.** `label` menyebut tahap dari sisi *apa yang
sudah selesai*; `label_menunggu` menyebutnya dari sisi *apa yang sedang
ditunggu*. Petugas kantor terbiasa dengan cara kedua ("Menunggu Jadwal Panitia
A"), sedangkan struktur data memakai cara pertama. Aturan pemakaian ada di
bagian 6.

**PKKPR mendahului pengukuran.** Berkas yang masuk tanpa PKKPR dikembalikan ke
pemohon untuk mendaftar PKKPR lebih dahulu. Kondisi itu tidak perlu ditandai
khusus — cukup terbaca dari `tgl_permohonan` terisi sementara `tgl_pkkpr`
kosong. Jangan catat sebagai kendala; kendala disediakan untuk hambatan di luar
alur normal.

Seluruh bagian aplikasi — form input, timeline detail, label grafik, header
tabel, header export — membaca dari config ini. Ini persyaratan keras: nama dan
urutan tahap kemungkinan masih berubah, dan perubahan itu harus cukup di satu
file.

## 3. Skema database

```
jenis_instansi
  id
  kode                string, unique   (dibuat otomatis dari nama, lalu dikunci)
  nama                string
  aktif               boolean default true
  timestamps

instansi
  id
  nama                string
  jenis_instansi_id   FK jenis_instansi
  aktif               boolean default true
  timestamps

bidang
  id
  nomor_urut          string, unique
  nama_aset           string
  instansi_id         FK instansi (pemegang hak)
  pengguna_barang     string, nullable      (perangkat daerah pemakai aset)
  penggunaan          string, nullable      (kantor, sekolah, puskesmas, dll)
  desa                string
  kecamatan           string, nullable
  luas_m2             decimal(12,2), nullable
  jenis_hak           string(50), nullable  (Hak Pakai, Hak Pengelolaan)
  alas_hak            string, nullable      (SPPHT, surat hibah, SKST)
  tgl_alas_hak        date, nullable
  asal_usul           string(50), nullable  (Hibah, Pembelian)
  nomor_berkas_kkp    string, nullable
  nomor_sertipikat    string, nullable
  tahun_target        year
  tgl_diproses        date, nullable   (penanda bidang masuk pantauan)
  keterangan          text, nullable
  tgl_permohonan      date, nullable
  tgl_pkkpr           date, nullable
  tgl_pengukuran      date, nullable
  tgl_peta_analisis   date, nullable
  tgl_panitia_a       date, nullable
  tgl_sk              date, nullable
  tgl_sertipikat      date, nullable
  tgl_serah_terima    date, nullable
  status              enum(belum_diproses, proses, selesai, diserahkan,
                           terkendala) default proses
  timestamps
  softDeletes
  index: instansi_id, tahun_target, status

kendala
  id
  bidang_id           FK bidang
  kategori            enum(berkas_kurang, menunggu_pemohon, sengketa,
                           overlap_bidang, kawasan_hutan, lainnya)
                      default berkas_kurang
  uraian              text
  tanggal_catat       date
  tanggal_selesai     date, nullable
  dicatat_oleh        string
  timestamps

users
  standar Laravel, ditambah:
  instansi_id         FK instansi, nullable
  role                enum(admin, operator, viewer)
```

**Kolom yang datang dari daftar aset pemda.** `pengguna_barang`, `jenis_hak`,
`alas_hak`, `tgl_alas_hak`, `asal_usul`, dan `nomor_sertipikat` ditambahkan
agar daftar aset tanah milik pemda dapat dimuat apa adanya. Alas hak adalah
dasar penguasaan sebelum sertipikat terbit — inilah berkas yang diperiksa di
loket, jadi ia dicatat terpisah dari `nomor_sertipikat`. Kolom akuntansi milik
daftar barang milik daerah (harga perolehan, nilai buku) sengaja tidak diambil:
aplikasi ini memantau sertipikasi, bukan menilai aset.

`instansi_id` tetap badan hukum pemegang hak, bukan perangkat daerah — subjek
hak pada sertipikat Hak Pakai adalah pemerintah daerah. Perangkat daerah
pemakainya dicatat di `pengguna_barang` dan disediakan sebagai penyaring pada
daftar bidang.

`kecamatan` boleh kosong: daftar aset pemda hanya punya satu kolom letak, dan
sebagian besar barisnya berhenti di nama desa.

**Kolom `status` tidak diisi operator.** Nilainya dihitung otomatis dari
tanggal tahap dan kendala aktif, lewat observer saat bidang atau kendala
disimpan. Field status dihapus dari form input.

| nilai          | syarat                                                    |
|----------------|-----------------------------------------------------------|
| terkendala     | ada minimal satu kendala dengan `tanggal_selesai` kosong  |
| diserahkan     | `tgl_serah_terima` terisi                                 |
| selesai        | `tgl_sertipikat` terisi, `tgl_serah_terima` belum         |
| belum_diproses | `tgl_diproses` kosong                                     |
| proses         | selain itu                                                |

Urutan pemeriksaan sesuai tabel di atas — `terkendala` menang atas yang lain.

**Daftar aset bukan daftar pekerjaan.** Aset tanah pemda berjumlah ribuan
bidang; Kantor Pertanahan memilih sebagian untuk diproses tiap tahun.
`tgl_diproses` menandai bidang yang benar-benar dikerjakan, dan hanya bidang
itu yang dihitung dashboard. Tanpa pemisahan ini seluruh aset yang belum
bersertipikat terbaca "dalam proses" — angka yang menyesatkan pimpinan.

Penandanya tidak pernah diisi manual lewat form. Aturannya:

- bidang yang dibuat lewat aplikasi (form maupun impor Excel) langsung
  dipantau, sebab memang sedang dikerjakan;
- `impor:daftar-aset` menaruh barisnya di antrean dengan `tgl_diproses` kosong;
- operator memindahkan bidang dari antrean ke pantauan lewat kotak centang dan
  tombol pada daftar bidang;
- mengisi tanggal tahap pada bidang antrean menandainya dipantau dengan
  sendirinya — pekerjaannya jelas sudah jalan.

**Berkas tidak lengkap dicatat sebagai kendala, bukan sebagai tahap.** Kekurangan
berkas bisa muncul di tahap mana pun, jadi memodelkannya sebagai tahap berarti
menyisipkannya di banyak posisi. Bidang tetap menempel di tahap terakhirnya dan
ditandai punya kendala aktif. Kendala ditutup dengan mengisi `tanggal_selesai`,
bukan dengan menghapus baris, agar riwayatnya tersimpan.

## 4. Logika turunan (accessor pada model `Bidang`)

- `tahapBerlaku()` — mengembalikan `config('tahapan')` apa adanya
- `tahapAktif` — tahap terakhir yang tanggalnya terisi; null bila belum ada
- `tahapBerikut` — tahap setelah `tahapAktif`; null bila seluruh tahap terisi
- `penanggungJawab` — nilai `penanggung_jawab` dari `tahapBerikut`
- `umurHari` — selisih `tgl_permohonan` sampai `tgl_sertipikat`; bila sertipikat
  belum terbit, sampai hari ini; null bila `tgl_permohonan` kosong
- `persenProgres` — jumlah tanggal terisi dibagi 8 × 100
- `statusHitung` — nilai status sesuai tabel di bagian 3
- `kendalaAktif` — relasi kendala dengan `tanggal_selesai` kosong

Unit test wajib mencakup: bidang kosong, bidang tuntas, bidang dengan tanggal
terisi tidak berurutan (operator melewati satu tahap), dan `statusHitung` untuk
keempat nilainya.

## 5. Hak akses

- `admin` — kelola pengguna, master instansi, seluruh data bidang dan kendala
- `operator` — tambah/ubah/hapus bidang dan kendala. Hanya peran ini yang
  menulis data pemantauan.
- `viewer` — read-only atas SELURUH data lintas instansi. Tidak dibatasi ke
  instansinya sendiri; keterbukaan lintas instansi adalah tujuan aplikasi ini.

Gunakan Gate/Policy bawaan Laravel dan middleware `role:` buatan sendiri.

## 6. Halaman

### Aturan pemakaian label

`label_menunggu` dipakai di mana pun aplikasi menyebut kondisi berjalan sebuah
bidang: kolom tahap pada daftar bidang, tabel bidang terlama di dashboard, dan
label sumbu grafik bidang tertahan per tahap.

`label` dipakai di: timeline halaman detail, label input pada form, dan header
kolom export.

Bidang yang seluruh tahapnya terisi ditampilkan sebagai **"Sudah Diserahkan"**.

Bidang dengan kendala aktif diberi badge peringatan di samping label tahapnya,
berisi kategori kendala — contoh: *Menunggu Jadwal Panitia A — berkas kurang*.

### Dashboard (halaman utama — ini yang dilihat pimpinan)

- baris ringkas daftar aset: total aset, sedang diproses, belum diproses, dan
  aset yang sudah bersertipikat sebelum pantauan dimulai. Ini latar yang
  membedakan beban kerja dari isi lemari arsip.
- kartu angka: total bidang target tahun berjalan, sudah bersertipikat, sudah
  diserahkan, dalam proses, terkendala. Seluruh angka dashboard — kartu,
  grafik, maupun tabel bidang terlama — hanya menghitung bidang yang
  `tgl_diproses`-nya terisi.
- progress bar capaian terhadap target tahun berjalan
- grafik batang: jumlah bidang tertahan di tiap tahap, label sumbu dari
  `label_menunggu`
- grafik donat: bidang tertahan menurut pihak penanggung jawab (Kantor
  Pertanahan vs Pemohon), dihitung dari `penanggung_jawab` tahap berikutnya.
  Ini penting: tanpa pemisahan ini, Kantah selalu terlihat sebagai penyebab
  keterlambatan padahal sebagian bola ada di pemohon — terutama di tahap PKKPR
  yang bisa menggantung berbulan-bulan di OSS.
- rincian bidang terkendala menurut kategori kendala
- grafik batang: capaian per instansi pemilik aset
- tabel: 10 bidang terlama belum selesai — kolom nama aset, instansi, tahap
  aktif, tahap berikut, penanggung jawab, umur hari
- tanggal pemutakhiran data terakhir (`MAX(bidang.updated_at)`) ditampilkan
  jelas di bagian atas, dengan penanda visual bila lebih dari 14 hari lalu

### Daftar bidang

Tabel dengan filter: instansi, pengguna barang, pantauan, tahap aktif,
penanggung jawab, status, kategori kendala, tahun target. Pencarian nama aset
dan nomor urut, ditambah pencarian desa tersendiri. Desa dicari dengan
mengetik penggalan namanya — bukan daftar pilihan, sebab nama desa pada daftar
aset ditulis beragam dan jumlahnya ratusan. Sebutan "Desa" atau "Kelurahan" di
depan kata kunci dilepas lebih dulu, supaya nama yang disalin apa adanya dari
berkas pemda tetap ketemu. Pagination server-side 25 per halaman.

Halaman ini juga tempat memilih pekerjaan: tiap baris punya kotak centang, dan
sebuah bilah aksi menandai bidang terpilih sedang diproses pada tahun target
yang dipilih, atau mengembalikannya ke antrean. Tersedia pula pilihan
"seluruh hasil penyaringan" supaya satu perangkat daerah bisa ditandai
sekaligus tanpa mencentang halaman demi halaman. Bidang yang tanggal tahapnya
sudah terisi tidak bisa dikembalikan ke antrean. Aksi ini hanya untuk peran
yang boleh menulis; viewer melihat daftar yang sama tanpa kotak centang. Kolom: nomor urut, nama aset, instansi,
desa/kecamatan, luas, tahap aktif, penanggung jawab, umur hari, status. Filter
tersimpan di query string.

### Detail bidang

Identitas aset lengkap, lalu timeline vertikal 8 tahap. Tiap tahap menampilkan
label, unit pelaksana, dokumen dasar, dan tanggal. Tahap belum selesai
ditampilkan redup. Di bawahnya daftar kendala beserta kategori, uraian, tanggal
catat, dan tanggal selesai.

### Form tambah/edit bidang

Satu halaman. Bagian tanggal tahap di-render dengan loop atas
`config('tahapan')`; tiap input diberi teks bantu berisi unit pelaksana dan
dokumen dasar. Tidak ada field status — nilainya dihitung otomatis.

### Master instansi, jenis instansi, dan manajemen pengguna

Admin saja. CRUD sederhana.

Jenis instansi adalah data master, bukan daftar tetap di kode: admin bisa
menambah jenis baru (mis. BUMD, kementerian) dan mengubah namanya. Ketentuan:

- `kode` dibuat otomatis dari nama saat jenis ditambahkan, lalu **tidak pernah
  berubah** walau namanya diganti. Kode inilah yang dipakai kode aplikasi untuk
  menemukan jenis bawaan (`pemda`, `kantah`, `kejaksaan`), sehingga pimpinan
  bebas mengubah nama tampilan tanpa merusak apa pun.
- Jenis yang masih dipakai instansi tidak bisa dihapus. Untuk memensiunkannya,
  nonaktifkan — jenis nonaktif tidak lagi ditawarkan saat menambah instansi,
  tetapi instansi lama tetap utuh.
- Tiga jenis bawaan ditanam oleh migrasi, jadi pemasangan baru langsung punya
  pilihan tanpa perlu seeder.

### Impor daftar aset pemda

Data awal datang sebagai berkas keluaran aplikasi barang milik daerah — kop dua
baris, judul bertingkat dua, dan lembar terpisah untuk tanah yang sudah dan
belum bersertipikat. Berkas itu dibaca perintah konsol
`php artisan impor:daftar-aset <berkas.xlsx>`, bukan lewat halaman impor:
jumlah barisnya seribu lebih dan susunannya bukan template aplikasi ini.

Berkasnya sendiri tidak ikut repositori — `.gitignore` menyisihkan `docs/*.xlsx`
karena isinya data nyata yang berganti tiap tahun. Taruh kiriman terbaru dari
pemda di `docs/`, lalu sebut path-nya saat menjalankan perintah.

Ketentuan pembacaan:

- Lembar dikenali dari namanya. Kolom "Sertifikat" pada lembar yang sudah
  bersertipikat dibaca sebagai `nomor_sertipikat` dan `tgl_sertipikat`; pada
  lembar yang belum, kolom yang sama dibaca sebagai `alas_hak` dan
  `tgl_alas_hak`. Menyamakan keduanya berarti melaporkan tanah bersertipikat
  padahal belum.
- Seluruh baris masuk sebagai antrean (`tgl_diproses` kosong). Aset yang
  sertipikatnya sudah terbit sekaligus dicatat sudah diserahkan — begitu
  tercantum di daftar aset pemda, sertipikatnya memang sudah di tangan mereka.
- `nomor_urut` dibangun dari kode barang dan nomor register, sehingga perintah
  aman diulang. Kode barang dan register itu sendiri tidak jadi kolom —
  keduanya dititip di `keterangan` untuk rekonsiliasi.
- `tahun_target` bidang bersertipikat diambil dari tahun terbit sertipikatnya,
  bidang lain memakai tahun berjalan.
- `--pratinjau` menampilkan ringkasan tanpa menulis; `--perbarui` menimpa
  bidang yang nomor urutnya sudah ada, tanpa pernah mengosongkan tanggal
  sertipikat yang sudah dicatat operator.

### Export Excel

Daftar bidang sesuai filter aktif. Kolom tanggal tahap mengikuti urutan config,
memakai `label` sebagai header.

## 7. Ketentuan teknis

- Seeder: 3 instansi contoh, 4 pengguna (dua admin, satu operator, satu viewer),
  dan minimal 40 bidang. Sebaran meniru kondisi riil kantor: 2 menunggu PKKPR,
  2 menunggu pengukuran, 6 menunggu peta analisis, 7 menunggu Panitia A,
  5 siap diserahkan, 6 sudah diserahkan, sisanya berputar rata atas seluruh
  tahap, ditambah beberapa bidang yang masih antre di daftar aset. Beberapa bidang diberi
  kendala aktif dengan kategori bervariasi. Dashboard harus langsung bermakna
  setelah `db:seed`.
- Sebaran per tahap dihitung dengan satu query agregasi `SUM(CASE WHEN ...)`
  atas kedelapan kolom tanggal, bukan delapan query terpisah.
- Hindari N+1 pada halaman daftar dan dashboard. Eager load relasi instansi dan
  kendala aktif.
- Semua query dashboard di dalam `DashboardService`.
- Format tanggal tampilan `d M Y` dengan nama bulan Indonesia. Sediakan helper.
- Locale aplikasi `id`.

## 8. Tampilan responsif

Seluruh halaman wajib terpakai di ponsel, tablet, dan desktop. Alasannya
operasional: pimpinan membuka dashboard dari ponsel, dan operator Kantor
Pertanahan kerap memutakhirkan tanggal dari lapangan. Halaman yang hanya
nyaman di desktop membuat data telat masuk.

Titik henti mengikuti Tailwind: `< 640px` ponsel, `640–1023px` tablet (`sm`,
`md`), `>= 1024px` desktop (`lg`).

Ketentuan:

- **Halaman tidak pernah menggulir mendatar.** Isi yang lebar — tabel, grafik,
  blok kode — menggulir di dalam wadahnya sendiri, bukan menggeser seluruh
  halaman.
- **Tabel lebar disajikan ulang, bukan sekadar dikecilkan.** Daftar bidang dan
  tabel bidang terlama tampil sebagai kartu satu per baris di ponsel, dan
  sebagai tabel mulai `md`. Kolom sekunder (luas, penanggung jawab, tahap
  berikut) disembunyikan pada layar sempit, tidak dipadatkan sampai tak
  terbaca. Seluruh nilai yang disembunyikan tetap tersedia di halaman detail
  dan di export.
- **Menu utama menjadi panel lipat** di bawah `md`, dengan tombol yang menyebut
  status buka/tutupnya untuk pembaca layar.
- **Target sentuh minimal 44×44 px** untuk tombol dan tautan aksi. Tombol utama
  melebar penuh di ponsel.
- **Form satu kolom di ponsel**, dua kolom mulai `sm`. Input tanggal tahap tetap
  dirender dari `config('tahapan')` dengan teks bantu yang sama.
- **Grafik Chart.js responsif**, tingginya mengecil di ponsel, dan angka yang
  dibawanya tetap tersedia dalam bentuk tabel/daftar teks.
- **Kartu angka dashboard dua kolom di ponsel**, empat kolom mulai `lg`.

Verifikasi dilakukan pada tiga lebar: **375px**, **768px**, dan **1440px**.
Setiap halaman baru wajib diperiksa pada ketiganya sebelum dinyatakan selesai.

## 9. Tahapan pengerjaan

Kerjakan berurutan. Berhenti dan tunjukkan hasil di setiap batas, tunggu
konfirmasi sebelum lanjut.

**TAHAP A** — `config/tahapan.php`, migrasi, model, accessor turunan, factory,
seeder, unit test accessor.

**TAHAP B** — Auth, role, middleware, Policy, CRUD bidang dan kendala, master
instansi dan pengguna.

**TAHAP C** — `DashboardService`, query agregasi, halaman dashboard dan grafik.

**TAHAP D** — Daftar bidang dengan filter, halaman detail, export Excel.
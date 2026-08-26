# E-Learning Lite

E-Learning Lite adalah prototype aplikasi web berbasis Laravel 12 yang menjadi antarmuka alternatif untuk E-Learning UAD/Moodle. Aplikasi ini menggunakan Moodle Web Service untuk login, mengambil data pembelajaran, dan mengirim data pembelajaran tertentu sesuai hak akses pengguna serta layanan Moodle yang tersedia.

Data akademik utama tetap berada di E-Learning UAD/Moodle. SQLite menyimpan completion gamifikasi E-Learning Lite untuk perhitungan poin dan leaderboard lintas akun; session dan cache memakai file pada setup default.

## Yang Perlu Diketahui

- Stack utama: Laravel 12, PHP 8.2, Blade, Vite, Tailwind CSS v4, Docker Compose, dan Moodle Web Service.
- Moodle terhubung melalui konfigurasi di `.env`.
- Jangan commit `.env`, token Moodle, password Moodle, atau credential asli lain.
- Mode Docker memakai port:
  - Aplikasi: `http://localhost:8081`
- Docker Compose default tidak menjalankan MySQL/phpMyAdmin.
- Session/cache/queue memakai driver non-database (`file`/`sync`) pada setup default.

## Persyaratan

Pastikan sudah terpasang:

| Software | Versi Minimal | Keterangan |
|---|---:|---|
| PHP | 8.2 | Untuk Laravel jika berjalan tanpa Docker |
| Composer | 2.x | Dependency PHP |
| Docker Desktop | 20.10+ | Disarankan untuk menjalankan app |
| Docker Compose | 2.x | Biasanya sudah termasuk Docker Desktop |
| Node.js | 18+ | Build asset Vite/Tailwind |
| Git | 2.x | Clone repository |

Untuk Windows, gunakan PowerShell atau terminal lain yang biasa dipakai. Docker Desktop perlu dalam keadaan aktif.

## Step by Step Menjalankan dengan Docker

Cara ini disarankan karena aplikasi berjalan di container tanpa perlu menyiapkan database lokal.

### 1. Clone project

```bash
git clone <URL_REPOSITORY>
cd laravel-moodle-app
```

### 2. Buat file environment

```bash
cp .env.example .env
```

Jika di PowerShell Windows:

```powershell
Copy-Item .env.example .env
```

### 3. Isi konfigurasi `.env`

Minimal konfigurasi lokal:

```env
APP_NAME="E-Learning Lite"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8081

DB_CONNECTION=sqlite

SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync

MOODLE_BASE_URL=https://domain-moodle.example/
MOODLE_SERVICE_NAME=
MOODLE_TOKEN=
MOODLE_USERNAME=
MOODLE_PASSWORD=
MOODLE_VERIFY_SSL=true

ADMIN_MOODLE_USERNAMES=
```

Catatan Moodle:

- `MOODLE_BASE_URL` diisi URL Moodle yang akan dihubungkan.
- `MOODLE_SERVICE_NAME` diisi nama service Moodle untuk login/akses data jika dibutuhkan implementasi.
- `MOODLE_TOKEN` dapat diisi jika ada token service dari Moodle.
- `ADMIN_MOODLE_USERNAMES` dapat diisi daftar username Moodle yang dianggap admin lokal, pisahkan dengan koma jika lebih dari satu

### 4. Build dan jalankan container

```bash
docker compose up -d --build
```

Jika command `docker compose` belum tersedia, coba:

```bash
docker-compose up -d --build
```

### 5. Generate app key

```bash
docker compose exec app php artisan key:generate
```

### 6. Jalankan migrasi database

```bash
docker compose exec app php artisan migrate
```

Migrasi membuat tabel completion gamifikasi pada `database/database.sqlite`.

### 7. Buat storage link

```bash
docker compose exec app php artisan storage:link
```

### 8. Install dan build asset frontend

Jalankan dari host/laptop, bukan dari container:

```bash
npm install
npm run build
```

Untuk development dengan hot reload:

```bash
npm run dev
```

### 9. Buka aplikasi

Akses:

```text
http://localhost:8081
```

Login menggunakan akun E-Learning UAD/Moodle sesuai konfigurasi integrasi yang tersedia.

## Product Knowledge dan Akses Fitur

Bagian ini menjadi dokumentasi operasional untuk memahami fitur, akses, dan alur penggunaan E-Learning Lite. Dokumen pilar tetap berada di `docs/SRS.md` dan `docs/Design_System.md`, sedangkan README ini dipakai untuk panduan penggunaan project sehari-hari.

### Prinsip Sistem

- E-Learning Lite adalah antarmuka alternatif untuk E-Learning UAD/Moodle.
- Data akademik utama seperti course, peserta, materi, kuis, tugas, nilai, dan progress tetap berasal dari E-Learning UAD/Moodle.
- Integrasi dilakukan melalui Moodle Web Service sesuai hak akses pengguna dan layanan Moodle yang tersedia.
- Setiap aktivitas yang selesai bernilai 10 poin tanpa bonus poin; completion gamifikasi disimpan di SQLite agar leaderboard dapat dibaca lintas akun.
- Penyimpanan lokal dipakai untuk completion gamifikasi E-Learning Lite serta kebutuhan teknis seperti session file, cache, log, dan konfigurasi aplikasi.
- E-Learning Lite tidak menjadi LMS lokal pengganti Moodle dan tidak menjadi sumber data akademik utama.

### Role dan Akses

| Role | Akses Utama |
|---|---|
| Dosen | Melihat dashboard, daftar course Moodle, detail course, peserta, nilai, progress, leaderboard, dan mengelola data pembelajaran yang didukung layanan Moodle. |
| Mahasiswa | Melihat dashboard, course yang diikuti, materi, kuis/tugas, nilai, progress, deadline, dan ringkasan capaian gamifikasi jika data tersedia. |

Catatan:

- Hak akses mengikuti akun E-Learning UAD/Moodle.
- Admin bukan aktor utama dalam SRS terbaru.
- Jika sebuah fitur tidak tampil atau gagal dijalankan, kemungkinan layanan Moodle Web Service, token/service, hak akses akun, atau data Moodle belum mendukung fitur tersebut.

### Fitur Web Utama

| Fitur | URL | Keterangan |
|---|---|---|
| Login | `/` atau `/login` | Login menggunakan akun E-Learning UAD/Moodle. |
| Logout | `/logout` | Mengakhiri sesi pengguna. |
| Dashboard | `/dashboard` | Ringkasan course, progress, deadline, profil, dan feedback gamifikasi jika data tersedia. |
| Notifikasi | `/notifications` | Halaman ringkasan aktivitas, deadline, serta pemberitahuan tugas yang sudah dinilai jika data Moodle tersedia. |
| Profil | `/profile` | Halaman informasi akun Moodle dan arahan pengaturan akun. |
| Daftar Course Moodle | `/courses` | Menampilkan course dari E-Learning UAD/Moodle sesuai akses akun. |
| Detail Course | `/courses/{courseId}` | Menampilkan informasi, topik, materi, dan aktivitas course dari Moodle. |
| Detail Materi/Aktivitas | `/courses/{courseId}/modules/{moduleId}` | Menampilkan detail module Moodle, termasuk lampiran tugas, upload file jawaban, dan Catatan sebagai komentar pengajuan jika assignment mendukung. |
| Peserta Kursus | `/enrolled-users` | Menampilkan peserta terdaftar jika data course dan peserta tersedia. |
| Nilai Kursus | `/grades` | Menampilkan nilai/grade jika data tersedia dari Moodle. |

### Arsitektur Informasi Dosen

Tampilan dosen mengikuti struktur utama berikut:

| Area | Isi | Status Implementasi |
|---|---|---|
| Login | Login akun E-Learning UAD/Moodle | Tersedia. |
| Dashboard | Ringkasan kursus dan pintasan ke notifikasi/profil | Tersedia sebagai halaman utama setelah login. |
| Kursus | Daftar kursus, detail kursus, informasi kursus, peserta, materi, kuis, tugas, nilai, progress mahasiswa, dan leaderboard kursus | Daftar kursus, peserta, dan nilai tersedia. Materi, kuis, tugas, progress, dan leaderboard mengikuti data/layanan Moodle Web Service yang tersedia. |
| Notifikasi | Aktivitas mahasiswa, deadline aktivitas, tugas/kuis perlu ditinjau, dan informasi sistem | Tersedia sebagai halaman `/notifications`; data detail mengikuti layanan Moodle Web Service. |
| Profil | Informasi akun, pengaturan akun, ubah password, logout | Tersedia sebagai halaman `/profile`; pengaturan akun/ubah password mengikuti mekanisme E-Learning UAD/Moodle. |

### Fitur Integrasi Moodle

| Fitur | Sumber/Tujuan Data | Catatan |
|---|---|---|
| Autentikasi | Moodle Web Service | Menggunakan akun E-Learning UAD/Moodle. |
| Daftar course | Moodle Web Service | Berdasarkan course yang dapat diakses akun pengguna. |
| Detail course | Moodle Web Service | Bergantung data course yang disediakan Moodle. |
| Peserta course | Moodle Web Service | Tampil jika endpoint/data peserta tersedia. |
| Nilai/grade | Moodle Web Service | Tampil jika data nilai tersedia dan pengguna berhak melihatnya. |
| Pengumpulan tugas | Moodle Web Service | File disimpan sebagai submission tugas; `Catatan` dikelola melalui Comment API Moodle. |
| Feedback gamifikasi | SQLite E-Learning Lite | Setiap completion aktivitas unik bernilai 10 poin; bukan sumber data akademik Moodle. |

### Alur Penggunaan Singkat

1. Pengguna membuka `/login`.
2. Pengguna login dengan akun E-Learning UAD/Moodle.
3. Sistem mengambil data pengguna dan course melalui Moodle Web Service.
4. Pengguna masuk ke dashboard.
5. Pengguna membuka daftar course di `/courses`.
6. Kursus baru dibuat melalui E-Learning UAD/Moodle dan akan tampil di Lite jika akun memiliki akses.
7. Mahasiswa melihat ringkasan progress, deadline, nilai, dan feedback gamifikasi; poin serta leaderboard dibaca dari completion yang tercatat di E-Learning Lite.

### Batasan Fitur

- Pembuatan kursus baru dari Lite tidak tersedia karena service Moodle yang digunakan saat ini tidak menyediakan fitur tersebut.
- Pengelolaan kuis, materi/resource, dan submit tugas dari Lite bergantung pada hak akses pengguna, konfigurasi E-Learning UAD/Moodle, dan layanan Moodle Web Service yang tersedia.
- Materi dan aktivitas dibuka terlebih dahulu melalui halaman E-Learning Lite. Link ke E-Learning UAD/Moodle hanya menjadi fallback untuk aktivitas interaktif yang membutuhkan engine Moodle, seperti pengerjaan kuis atau submit tugas.
- Untuk assignment, mahasiswa dapat melihat lampiran tugas, aturan pengajuan, draft file yang sudah tersimpan, menambahkan `Catatan` sebagai komentar pengajuan Moodle, mengganti file draft, dan melakukan final submit ke Moodle. Upload file mengikuti jumlah maksimum, ukuran maksimum, dan format berkas dari assignment Moodle. Token/service Moodle harus mengizinkan `core_comment_get_comments` dan `core_comment_add_comments`. Jika jawaban sudah final submit, upload dan submit ulang dinonaktifkan di Lite.
- Notifikasi tugas yang sudah dinilai dibentuk dari laporan nilai Moodle. Notifikasi tampil pada filter `Semua` dan `Nilai Tugas`; filter `Nilai Tugas` khusus memuat tugas yang sudah dinilai. Tombol `Lihat Nilai` membuka halaman Detail Nilai pada tab Tugas untuk kursus terkait.
- Badge notifikasi diperbarui pada seluruh halaman mahasiswa yang memiliki topbar ketika halaman dimuat, setiap 60 detik saat tab aktif, dan saat tab browser kembali aktif. Halaman Notifikasi menampilkan jumlah baru pada filter `Semua`, `Batas Waktu`, dan `Nilai Tugas` sebelum statusnya ditandai dibaca.
- Status baca disimpan sebagai kunci teknis per mahasiswa pada tabel `notification_reads`, bukan sebagai salinan data akademik. Jalankan migrasi saat deploy dan pertahankan database aplikasi agar notifikasi lama tidak kembali dianggap baru.
- Jika Moodle Web Service tidak dapat diakses, aplikasi menampilkan pesan kegagalan dan fitur yang membutuhkan Moodle tidak dapat berjalan.
- Daftar peserta dan struktur aktivitas gamifikasi tetap bergantung pada data Moodle yang bisa dibaca.
- Notifikasi email tidak dibuat oleh E-Learning Lite; email mengikuti mekanisme E-Learning UAD/Moodle jika tersedia.
- Prototype ini diutamakan untuk laptop/PC. Akses HP hanya tambahan.

## Route Utama

| URL | Fungsi |
|---|---|
| `/` atau `/login` | Login |
| `/dashboard` | Dashboard |
| `/notifications` | Notifikasi dan informasi sistem |
| `/notifications/unread-summary` | Ringkasan jumlah notifikasi belum dibaca untuk badge topbar |
| `/profile` | Profil akun Moodle |
| `/courses` | Daftar kursus Moodle |
| `/courses/{courseId}` | Detail course, materi, dan aktivitas dari Moodle |
| `/courses/{courseId}/modules/{moduleId}` | Detail materi atau aktivitas di dalam Lite |
| `/enrolled-users` | Daftar pengguna terdaftar pada course jika data tersedia |
| `/grades` | Nilai/grade jika data tersedia |

Route lengkap dapat dicek dengan:

```bash
docker compose exec app php artisan route:list
```

Atau tanpa Docker:

```bash
php artisan route:list
```

## Perintah Harian

Melihat container:

```bash
docker compose ps
```

Melihat log aplikasi:

```bash
docker compose logs app
```

Masuk shell container:

```bash
docker compose exec app bash
```

Membersihkan cache Laravel:

```bash
docker compose exec app php artisan optimize:clear
```

Menjalankan test:

```bash
docker compose exec app php artisan test
```

Build asset setelah mengubah CSS/JS:

```bash
npm run build
```

## Troubleshooting

### Aplikasi tidak bisa dibuka di `localhost:8081`

Pastikan container berjalan:

```bash
docker compose ps
```

Jika belum berjalan:

```bash
docker compose up -d
```

### Error koneksi database

Setup default menggunakan SQLite untuk completion gamifikasi. Jika muncul error seperti `Base table or view not found`, periksa konfigurasi database dan pastikan migrasi sudah dijalankan.

Gunakan konfigurasi ini:

```env
DB_CONNECTION=sqlite
SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync
```

Lalu bersihkan config dan jalankan migrasi:

```bash
docker compose exec app php artisan config:clear
docker compose exec app php artisan migrate
docker compose restart app
```

### Tampilan CSS tidak rapi

Jalankan build asset:

```bash
npm install
npm run build
```

Jika sedang development:

```bash
npm run dev
```

### Login Moodle gagal

Cek:

- `MOODLE_BASE_URL` benar dan bisa diakses dari internet.
- Username/password Moodle valid.
- Nama service/token Moodle sesuai konfigurasi Moodle Web Service.
- `MOODLE_VERIFY_SSL=false` hanya dipakai untuk kasus sertifikat SSL bermasalah di lingkungan development.

Setelah mengubah `.env`, bersihkan config:

```bash
docker compose exec app php artisan config:clear
```

### Port bentrok

Docker app memakai `8081`. Jika port bentrok, ubah mapping port di `docker-compose.yml`.

### Perubahan `.env` tidak terbaca

Jalankan:

```bash
docker compose exec app php artisan config:clear
docker compose restart app
```

## Dokumentasi Project

- `docs/SRS.md`: dokumen pilar requirement/source of truth kebutuhan sistem.
- `docs/Design_System.md`: dokumen pilar design system dan acuan visual.
- `README.md`: dokumentasi operasional, setup, product knowledge, akses fitur, dan troubleshooting.
- `AGENTS.md`: aturan kerja saat mengubah project.

## Catatan Keamanan

- Jangan menaruh credential asli di README, kode, atau commit Git.
- Simpan token/password hanya di `.env` lokal.
- Untuk production, gunakan HTTPS dan kebijakan akses Moodle yang disetujui admin.

### Error: `vendor/autoload.php` tidak ditemukan

Jika container terus restart dan log menampilkan:

```text
Failed opening required '/app/vendor/autoload.php'
```

Pastikan dependency PHP sudah terinstall:

```bash
composer install
```

Lalu rebuild container:

```bash
docker compose up -d --build
```

### Error: `Vite manifest not found`

Jika muncul error:

```text
Vite manifest not found at:
/app/public/build/manifest.json
```

Pastikan asset frontend sudah dibangun:

```bash
npm install
npm run build
```

Lalu bersihkan cache Laravel:

```bash
docker compose exec app php artisan optimize:clear
docker compose restart app
```

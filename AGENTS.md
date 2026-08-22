# AGENTS.md

Panduan ringkas untuk agent/collaborator saat mengubah project E-Learning Lite.

## Ringkasan Project

- E-Learning Lite adalah prototype Laravel 12 + Blade + Vite + Tailwind CSS v4 yang menjadi antarmuka alternatif untuk E-Learning UAD/Moodle.
- Mahasiswa adalah satu-satunya aktor. Jangan menambah fitur, menu, halaman, atau hak akses khusus Dosen maupun Admin.
- E-Learning UAD/Moodle tetap menjadi sumber data akademik utama.
- Penyimpanan lokal hanya untuk kebutuhan teknis seperti session, cache, log, atau konfigurasi; bukan sebagai pengganti data Moodle.

## Source of Truth

Gunakan acuan berikut tanpa menduplikasi detailnya ke file ini:

1. Request user aktif: tujuan dan ruang lingkup perubahan saat ini.
2. `docs/SRS.md`: requirement, aktor, scope, batasan, integrasi Moodle, gamifikasi, dan nonfunctional requirement.
3. `docs/Information_Architecture.md`: hierarki halaman dan navigasi.
4. `docs/User_Flow/*.md`: urutan interaksi, decision, retry, deadline, error, hasil akhir, poin, dan badge.
5. `docs/Design_System.md`: font, visual, layout, token, komponen, copy UI, responsive behavior, dan accessibility.
6. `README.md`: setup, environment, route, cara menjalankan, dan penggunaan aplikasi.

Jika terdapat konflik, utamakan request user terbaru. Gunakan SRS untuk scope, User Flow untuk perilaku, Information Architecture untuk struktur, dan Design System untuk tampilan. Laporkan konflik yang tidak dapat diselesaikan dari konteks.

## Aturan Inti

- Baca dokumen acuan dan kode terkait sebelum mengubah implementasi.
- Pertahankan perubahan user yang tidak terkait; jangan mereset atau menimpa worktree secara luas.
- Gunakan pola Laravel dan struktur project yang sudah ada.
- Letakkan komunikasi Moodle di `app/Services/MoodleService.php` atau abstraksi Moodle existing, bukan di Blade.
- Jalankan fitur akademik hanya jika Moodle Web Service dan hak akses mahasiswa mendukungnya.
- Jangan membuat data akademik atau gamifikasi permanen lokal sebagai fallback Moodle.
- Jangan hardcode atau mengekspos credential, token, password, URL rahasia, atau isi `.env`.
- Validasi input di server dan jangan render input user/data Moodle sebagai HTML mentah tanpa sanitasi.
- Tampilkan pesan yang mudah dipahami jika Moodle gagal, timeout, atau tidak mendukung aksi tertentu.
- Seluruh UI harus mengikuti `docs/Design_System.md`, termasuk Instrument Sans, Bahasa Indonesia, Lucide icons, responsive behavior, dan accessibility.
- Jangan menambah fitur di luar scope hanya karena route, endpoint, atau komponen lama masih tersedia.

## Sinkronisasi Dokumentasi

Perbarui dokumen terkait hanya jika user meminta atau perubahan implementasi memang mengubah informasi tersebut:

- Perubahan requirement/scope/integrasi: `docs/SRS.md`.
- Perubahan hierarki/navigasi: `docs/Information_Architecture.md`.
- Perubahan alur/decision/state: file terkait di `docs/User_Flow/`.
- Perubahan visual/komponen/layout: `docs/Design_System.md`.
- Perubahan setup/route/cara pakai: `README.md`.

## Checks

Jalankan check yang relevan dan proporsional:

- `php artisan test` untuk backend, route, service, auth/session, submission, progress, gamifikasi, atau flow penting.
- `npm run build` untuk CSS, JavaScript, Vite, Blade, font, ikon, atau asset.
- `php artisan route:list` untuk perubahan route.
- `php -l path/to/file.php` untuk file PHP/Blade bila diperlukan.
- Untuk dokumentasi saja, validasi struktur, link/path, sintaks Mermaid, dan whitespace; test aplikasi tidak wajib.

## Laporan Akhir

Sampaikan secara ringkas:

- File yang diubah.
- Ringkasan perubahan.
- Check yang dijalankan.
- Asumsi atau konflik jika ada.

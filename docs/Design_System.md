# Design System E-Learning Lite v3

**Status:** Acuan visual final untuk desain yang telah tersedia  
**Aktor:** Mahasiswa  
**Font utama:** Instrument Sans

## 1. Tujuan

Design system ini menjadi source of truth visual E-Learning Lite agar seluruh halaman mahasiswa konsisten dengan desain final. Karakter utama antarmuka adalah ringan, lapang, edukatif, fokus pada aktivitas belajar, dan menggunakan gamifikasi secara terukur.

Referensi visual yang sudah final mencakup:

1. Login.
2. Dashboard mahasiswa.
3. Detail kursus.
4. Detail materi dan status penyelesaian.
5. Pengerjaan serta status pengumpulan tugas.
6. Pengerjaan serta status penyelesaian kuis.
7. Detail nilai.
8. Pencapaian, leaderboard, badge, dan daftar semua badge.

Halaman yang belum memiliki desain final harus menyusun ulang pola, token, dan komponen dalam dokumen ini; bukan memperkenalkan gaya visual baru.

## 2. Prinsip Visual

- Gunakan kanvas utama putih dengan sidebar biru sangat muda.
- Navy menjadi warna identitas dan aksi utama; biru terang menjadi aksen ikon, progress, dan gamifikasi.
- Gunakan ruang kosong yang cukup agar halaman tidak terasa seperti dashboard administratif yang padat.
- Gunakan border tipis, radius kecil hingga sedang, dan shadow lembut.
- Informasi utama harus terlihat dalam satu hierarki yang jelas: judul, ringkasan, konten, lalu aksi.
- Status selalu dibedakan dengan warna pastel dan label teks; warna tidak boleh menjadi satu-satunya pembeda.
- Gamifikasi harus terasa positif dan motivasional tanpa mengambil fokus dari pembelajaran.
- Jangan menampilkan pola atau istilah khusus Dosen karena aktor E-Learning Lite hanya Mahasiswa.

## 3. Design Tokens

### 3.1 Color Palette

Nilai berikut dinormalisasi dari desain final dan digunakan sebagai token global.

| Token | Nilai | Penggunaan |
|---|---|---|
| `--color-navy-900` | `#102E52` | sidebar aktif, tombol utama, teks navy, option terpilih |
| `--color-navy-800` | `#17375E` | hover pada aksi utama |
| `--color-blue-600` | `#2167D5` | ikon aktivitas, link, progress, elemen gamifikasi |
| `--color-blue-500` | `#2F73E0` | aksen interaktif dan focus ring |
| `--color-blue-200` | `#A9C9F5` | dashed border upload dan aksen dekoratif |
| `--color-blue-100` | `#D8E7FF` | panel informasi, chip, ikon berlatar |
| `--color-blue-50` | `#EEF4FF` | sidebar, panel deadline, baris opsi, surface soft |
| `--color-white` | `#FFFFFF` | background utama, card, modal, input |
| `--color-ink` | `#0A0A0A` | judul utama dan body dengan kontras tinggi |
| `--color-text` | `#102E52` | teks utama bernuansa brand |
| `--color-muted` | `#858585` | navigasi nonaktif, metadata, helper text |
| `--color-border` | `#D9D9D9` | border card, tabel, input, divider |
| `--color-success` | `#00A83B` | ikon dan teks sukses |
| `--color-success-soft` | `#B9FBCB` | status selesai/dinilai/diperoleh |
| `--color-warning` | `#A86700` | teks status belum selesai |
| `--color-warning-soft` | `#FFE3AD` | status belum selesai/dikumpulkan/dikerjakan |
| `--color-danger` | `#B90000` | error, batas waktu berakhir, file berisiko |
| `--color-danger-soft` | `#FFDCDC` | deadline, error panel, ikon file PDF |
| `--color-gold` | `#FFBC28` | poin, peringkat, dan reward |
| `--color-overlay` | `rgba(255, 255, 255, 0.72)` | backdrop modal dengan blur |

Aturan warna:

- Navy digunakan pada satu aksi dominan dalam satu area.
- Biru terang tidak menggantikan navy untuk tombol utama.
- Green hanya untuk hasil positif yang sudah tercapai.
- Yellow/orange hanya untuk status tertunda atau belum selesai.
- Red hanya untuk error, deadline kritis, atau penghapusan.
- Teks panjang tidak menggunakan warna muted apabila kontrasnya menjadi rendah.

### 3.2 Typography

Seluruh antarmuka wajib menggunakan **Instrument Sans**.

```css
font-family: "Instrument Sans", ui-sans-serif, system-ui, -apple-system,
  BlinkMacSystemFont, "Segoe UI", sans-serif;
```

Bobot yang digunakan: `400`, `500`, `600`, dan `700`. Hindari bobot `800` atau `900` kecuali aset visual memang membutuhkannya.

| Style | Ukuran / Line height | Bobot | Penggunaan |
|---|---|---|---|
| Display | `40px / 48px` | 700 | nama produk pada login |
| Heading 1 | `32px / 40px` | 700 | sambutan dashboard, judul status utama |
| Heading 2 | `26px / 34px` | 700 | judul halaman dan modal |
| Heading 3 | `22px / 28px` | 700 | judul section atau aktivitas |
| Title | `18px / 24px` | 600–700 | judul card, tabel, panel |
| Body | `16px / 24px` | 400–500 | isi utama dan form |
| Body Small | `14px / 20px` | 400–600 | metadata, isi card, label status |
| Caption | `12px / 16px` | 400–500 | ukuran file, tanggal badge, helper text |

Aturan tipografi:

- Gunakan sentence case untuk judul dan tombol.
- Uppercase hanya dipakai pada tab pendek seperti `TOPIK`, `NILAI`, dan `PENCAPAIAN`.
- Heading menggunakan warna ink atau navy; body panjang menggunakan ink.
- Angka penting seperti poin, nilai, progress, dan peringkat memakai bobot `700`.
- Teks harus dapat membungkus dan tidak boleh keluar dari card.

### 3.3 Spacing

Gunakan skala dasar 4 px:

| Token | Nilai |
|---|---|
| `--space-1` | `4px` |
| `--space-2` | `8px` |
| `--space-3` | `12px` |
| `--space-4` | `16px` |
| `--space-5` | `20px` |
| `--space-6` | `24px` |
| `--space-8` | `32px` |
| `--space-10` | `40px` |
| `--space-12` | `48px` |

Pedoman umum:

- Padding konten desktop: `24–32px`.
- Jarak antar-section: `20–24px`.
- Jarak antar-card: `10–16px`.
- Padding card: `16–20px`.
- Padding modal: `32–40px` untuk modal status dan `16–24px` untuk modal daftar.

### 3.4 Radius, Border, dan Shadow

| Token | Nilai | Penggunaan |
|---|---|---|
| `--radius-sm` | `6px` | button, tab, status pill, nav aktif |
| `--radius-md` | `10px` | input, panel, item daftar |
| `--radius-lg` | `14px` | card utama dan modal kecil |
| `--radius-xl` | `16px` | login card dan modal status |
| `--border-default` | `1px solid #D9D9D9` | card, input, divider |
| `--border-strong` | `2px solid #102E52` | upload container, focus area penting |
| `--shadow-card` | `0 4px 14px rgba(16, 46, 82, 0.14)` | card dashboard dan header kursus |
| `--shadow-modal` | `0 4px 16px rgba(0, 0, 0, 0.20)` | modal dan panel elevated |

Shadow tidak dipakai pada setiap elemen. Panel sederhana, tabel, dan accordion cukup menggunakan border.

## 4. Layout Utama

### 4.1 App Shell Desktop

Desain final menggunakan artboard acuan `1440 × 900px`.

- Sidebar tetap di kiri dengan lebar `302px`.
- Area konten dimulai setelah sidebar dan memakai background putih.
- Padding horizontal area konten sekitar `22–32px`.
- Baris aksi global di bagian atas memiliki tinggi efektif sekitar `88–90px`.
- Ikon notifikasi dan profil diletakkan di kanan atas dengan jarak `24–28px`.
- Konten utama dapat menggunakan seluruh lebar yang tersisa; tidak memakai panel samping dashboard terpisah.
- Modal dipusatkan terhadap area konten, bukan terhadap keseluruhan viewport termasuk sidebar.

Class dasar yang direkomendasikan:

- `.app-shell`
- `.app-sidebar`
- `.app-main`
- `.app-topbar`
- `.page-content`

### 4.2 Sidebar

- Background: `--color-blue-50`.
- Lebar desktop: `302px`.
- Logo berada di kiri atas dalam kotak navy `48 × 48px`, radius `6–8px`, dengan ikon graduation cap putih.
- Nama produk berada sejajar dengan logo, warna navy, ukuran sekitar `21px`, bobot `700`.
- Item navigasi memiliki tinggi sekitar `53px`, radius `6px`, dan padding horizontal `10px`.
- Lebar item pada desktop sekitar `250px` dengan margin kiri/kanan `23px`.
- Item aktif memakai background navy, ikon dan teks putih.
- Item nonaktif transparan dengan ikon dan teks abu-abu.
- Logout ditempatkan di bagian bawah sidebar dan tetap terlihat saat konten utama scroll.

Urutan navigasi mahasiswa:

1. Dashboard.
2. Kursus Pembelajaran.
3. Notifikasi.
4. Profil Pengguna.
5. Logout pada bagian bawah.

Class yang direkomendasikan:

- `.sidebar-brand`
- `.sidebar-nav`
- `.sidebar-link`
- `.sidebar-link.is-active`
- `.sidebar-logout`

### 4.3 Topbar

- Topbar bersifat ringan tanpa card atau border bawah.
- Ikon global menggunakan bentuk outline hitam atau navy dengan ukuran visual `30–36px`.
- Area klik minimal `44 × 44px`.
- Gunakan ikon `bell` untuk notifikasi dan `circle-user-round` untuk profil.
- Hindari badge notifikasi apabila tidak ada data unread.

## 5. Komponen Dasar

### 5.1 Buttons

| Variant | Tampilan | Penggunaan |
|---|---|---|
| Primary | navy, teks putih | login, submit, selesai, kembali ke detail kursus |
| Secondary | soft blue, teks ink/navy | kembali, sebelumnya, aksi netral |
| Ghost | transparan, teks/icon navy | aksi ringan dalam header |
| Icon | kotak `44–56px`, soft blue atau putih | kembali, download, tutup, hapus file |
| Danger | red atau danger-soft | aksi destruktif atau error yang dapat ditindaklanjuti |

Aturan:

- Tinggi tombol standar `46–56px`.
- Radius tombol `6px`.
- Tombol utama memiliki padding horizontal minimal `24px`.
- Setiap icon-only button wajib memiliki accessible name.
- Gunakan state `hover`, `focus-visible`, `active`, dan `disabled` yang jelas.
- Jangan menggunakan warna status sebagai tombol jika elemen tersebut tidak interaktif.
- Jangan menyediakan tombol `Buka di Moodle`, `Buka di E-Learning UAD`, `Buka Aktivitas Asli`, atau aksi sejenis. Seluruh flow yang termasuk scope harus diselesaikan di E-Learning Lite.
- Tautan eksternal hanya boleh digunakan jika konten pembelajaran memang berupa URL eksternal. Gunakan label yang menjelaskan kontennya, bukan nama platform integrasi.

Class yang direkomendasikan:

- `.button`
- `.button-primary`
- `.button-secondary`
- `.button-ghost`
- `.icon-button`

### 5.2 Cards dan Panels

- Surface putih.
- Border `1px solid --color-border` untuk panel data sederhana.
- Shadow lembut untuk card yang perlu terangkat dari kanvas, seperti course card, login card, hero, dan modal.
- Radius `10–16px` sesuai tingkat penekanan.
- Hindari nested card berlapis lebih dari dua tingkat.

Class yang direkomendasikan:

- `.card`
- `.panel`
- `.course-card`
- `.summary-card`
- `.activity-card`
- `.modal-card`

### 5.3 Forms

- Label berada di atas field dengan jarak `8–10px`.
- Input standar memiliki tinggi sekitar `54px`, border abu-abu, radius `12px`, dan ikon opsional di sisi kiri.
- Focus ring memakai blue-500 dan tidak hanya mengandalkan perubahan warna border yang tipis.
- Textarea mengikuti lebar container dengan tinggi sesuai kebutuhan.
- Helper text memakai muted; error memakai danger dan teks penjelas.
- Password field menyediakan toggle visibility dengan accessible label.

Class yang direkomendasikan:

- `.form-field`
- `.field-label`
- `.input-shell`
- `.field-input`
- `.field-help`
- `.field-error`

### 5.4 Status Pills

| Status | Background | Teks |
|---|---|---|
| Selesai / Dinilai / Diperoleh | success-soft | green gelap |
| Belum selesai / Belum dikumpulkan / Belum dikerjakan | warning-soft | warning |
| Belum dinilai | navy | putih |
| Deadline / Error | danger-soft | danger atau navy sesuai konteks |
| Netral | blue-50 | navy |

- Tinggi pill mengikuti konten, sekitar `24–30px`.
- Radius `5–6px`, bukan pill bulat penuh untuk status rectangular pada desain final.
- Gunakan label yang spesifik; hindari hanya menulis `Aktif` atau `Tidak aktif` tanpa konteks.

### 5.5 Progress Bar

- Track memakai blue-50.
- Fill memakai navy atau blue-600.
- Tinggi `8–10px` dengan ujung membulat.
- Persentase diletakkan pada sisi kanan label `Progres Pembelajaran`.
- Selalu sediakan nilai tekstual agar informasi tidak hanya bergantung pada panjang bar.

### 5.6 Tabs

- Wrapper tabs memakai background blue-50 dan padding `5px`.
- Tab terbagi rata dalam satu baris.
- Tab aktif memakai navy dengan teks putih.
- Tab nonaktif memakai putih dengan teks navy.
- Radius tab `6px`.
- Pada mobile, tab boleh horizontal-scroll atau berubah menjadi select jika label tidak muat.

### 5.7 Tables

- Tabel memakai panel putih dengan border dan radius `10px`.
- Header tabel menggunakan teks navy/ink, bobot `700`, tanpa background gelap.
- Baris dipisahkan divider abu-abu tipis.
- Nilai dan status boleh memakai status pill.
- Tab kategori, seperti `Tugas` dan `Quiz`, diletakkan di atas tabel dan memakai underline pada tab aktif.
- Wrapper tabel wajib dapat di-scroll horizontal pada layar sempit.

### 5.8 Icons

Gunakan ikon outline dari Lucide dengan stroke konsisten sekitar `2px`. Jangan memakai emoji sebagai ikon UI.

| Fungsi | Ikon yang direkomendasikan |
|---|---|
| Brand/pendidikan | `graduation-cap` |
| Dashboard | `house` |
| Kursus | `book-open` |
| Notifikasi | `bell` |
| Profil | `circle-user-round` |
| Logout | `log-out` |
| Materi | `notebook-tabs` atau `book-open` |
| Tugas | `notepad-text` |
| Quiz | `alarm-clock` |
| Kembali | `undo-2` atau `arrow-left` |
| Upload | `cloud-upload` |
| Download | `download` |
| Sukses | `circle-check-big` |
| Deadline | `alarm-clock` |
| Poin | `star` |
| Peringkat | `trophy` |
| Pencapaian | `award` |

## 6. Pola Halaman

### 6.1 Login

- Desktop menggunakan split screen dua kolom dengan proporsi mendekati `50:50`.
- Panel kiri memakai blue-50 dan memusatkan logo besar, nama `E-Learning Lite`, serta tagline.
- Panel kanan putih dan memusatkan login card berukuran sekitar `474 × 552px` pada artboard acuan.
- Login card memiliki radius `16px`, padding sekitar `36px`, dan shadow lembut.
- Urutan: judul, deskripsi singkat, username, password, lalu tombol Login.
- Tombol Login memiliki lebar sekitar setengah hingga dua pertiga card dan rata tengah.
- Error autentikasi ditampilkan di dalam card, dekat dengan form, tanpa menggeser hierarki secara berlebihan.
- Pada mobile, kelompok logo, nama produk, dan tagline harus berjarak rapat dengan login card serta dipusatkan sebagai satu komposisi vertikal, tanpa pembagian tinggi layar yang menciptakan ruang kosong berlebihan.

Class yang direkomendasikan:

- `.login-page`
- `.login-brand-panel`
- `.login-brand-mark`
- `.login-card`
- `.login-form`

### 6.2 Dashboard Mahasiswa

- Gunakan hero horizontal soft blue dengan judul sambutan di kiri dan ilustrasi mahasiswa belajar di kanan.
- Hero memakai shadow lembut dan radius sekitar `10px`.
- Daftar kursus menggunakan grid tiga kolom pada desktop.
- Setiap course card memuat identitas singkat mata kuliah, nama course, progress bar, panel deadline, dan shortcut materi.
- Panel deadline memakai blue-50; tanggal deadline memakai danger-soft.
- Card menggunakan padding `10–20px`, radius `14px`, dan shadow card.
- Konten utama scroll; sidebar dan topbar tetap stabil.

Class yang direkomendasikan:

- `.dashboard-hero`
- `.course-grid`
- `.course-card`
- `.course-identity`
- `.course-progress`
- `.deadline-panel`
- `.deadline-item`

### 6.3 Detail Kursus

- Header course berupa card putih elevated yang memuat inisial course, nama course, dan tombol kembali. Pada mobile, tinggi card dipadatkan sekitar `84px` dengan inisial `44px` dan judul berskala `18–21px`.
- Di bawah header tampil label serta progress pembelajaran. Pada mobile, label progress memakai `16px` dan track dipadatkan menjadi sekitar `6px`.
- Gunakan tab `TOPIK`, `NILAI`, dan `PENCAPAIAN`.
- Ringkasan aktivitas menggunakan tiga card: Materi, Tugas, dan Quiz, masing-masing memuat jumlah selesai/total serta status. Pada mobile, container ikon ringkasan berukuran sekitar `40–42px` dan ikon baris aktivitas sekitar `20px` agar aset tidak mendominasi card.
- Topik mingguan menggunakan accordion. Header memuat rentang tanggal, jumlah aktivitas, dan chevron.
- Aktivitas di dalam accordion memakai ikon soft blue di kiri, nama aktivitas, dan status di kanan.
- Pada mobile, header accordion tetap satu baris: rentang topik di kiri serta jumlah aktivitas dan chevron di kanan. Chip rentang mengikuti lebar teks; rentang yang terlalu panjang memakai elipsis agar susunan tidak pecah. Baris aktivitas memakai ikon kecil di kiri serta nama dan status yang bertumpuk rapat di kanan.
- Seluruh baris aktivitas harus dapat diklik dengan target minimum `44px`.
- Saat aktivitas dibuka, pertahankan ikon dan nama aktivitas, ganti area status dengan spinner navy, beri permukaan soft blue, serta tandai baris sebagai sibuk sampai navigasi selesai. Hanya pilihan terakhir yang boleh memiliki state loading; jika aktivitas lain dipilih sebelum navigasi selesai, pulihkan state sebelumnya dan pindahkan indikator ke pilihan terbaru.

Class yang direkomendasikan:

- `.course-header-card`
- `.course-initial`
- `.course-progress-block`
- `.course-tabs`
- `.activity-summary-grid`
- `.topic-accordion`
- `.topic-activity-row`

### 6.4 Detail Materi

- Header halaman memuat ikon materi biru, judul, status di kanan, dan tombol kembali.
- Bagian lampiran memakai panel blue-50 dengan ikon file danger-soft, nama file, ukuran, dan tombol download.
- Deskripsi materi menggunakan body text dengan lebar baca yang nyaman.
- Aksi `Baca Materi` diletakkan di sisi kanan pada desktop.
- Pada modal pembaca materi, tombol `Kembali` menggunakan secondary soft blue, sedangkan tombol `Selesai` tetap menggunakan primary navy.
- Setelah materi selesai, status berubah menjadi `Sudah Diselesaikan` dan tampil modal keberhasilan.
- Pada desktop, baris lampiran materi menggunakan ukuran ringkas yang sama dengan lampiran tugas, dengan tinggi minimum sekitar 76 px. Pada mobile, gunakan ikon header 40 px dan judul 18 px; status ringkas berada sejajar dengan judul saat ruang cukup lalu membungkus secara natural untuk judul panjang. Lampiran memiliki tinggi minimum 72 px dan tombol aksi selebar konten.

Class yang direkomendasikan:

- `.material-header`
- `.attachment-panel`
- `.file-icon`
- `.material-description`
- `.material-action`

### 6.5 Pengerjaan Tugas

- Header memuat ikon tugas biru dan nama tugas.
- Detail tugas menampilkan `Deskripsi Tugas`, lalu `Instruksi Tugas` yang berisi instruksi dari sumber pembelajaran diikuti panduan pengumpulan dari aplikasi tanpa label atau penyebutan nama platform.
- Bagian `Lampiran` menampilkan berkas tambahan tugas yang tersedia bagi mahasiswa dari E-Learning UAD, lengkap dengan nama, ukuran, dan aksi unduh; tampilkan empty state hanya jika tidak ada berkas yang diberikan. Pada desktop, gunakan baris ringkas dengan tinggi minimum sekitar 76 px.
- Tombol `Kerjakan Tugas` menggunakan ukuran ringkas pada desktop dan tetap selebar konten pada mobile.
- Dropzone terdiri dari border luar navy dan area dalam blue-50 dengan dashed border biru.
- Area upload memuat ikon cloud-upload, instruksi drag-and-drop, link pemilih file, format, dan batas ukuran.
- File terpilih tampil dalam baris soft blue dengan ikon file, nama, ukuran, dan tombol hapus.
- Catatan opsional menggunakan textarea berukuran lebar.
- Pada tahap konfirmasi, tampilkan ringkasan tugas, batas pengumpulan, file, dan catatan dalam panel terfokus selebar maksimal 760 px. Posisikan panel di tengah pada desktop, sejajarkan baris aksi dengan lebar panel, dan gunakan lebar penuh pada mobile.
- Jika deadline terlewati, cegah pengumpulan dan tampilkan modal error waktu berakhir.
- Pada mobile, gunakan judul aktivitas 16 px yang dapat membungkus dengan lega, hilangkan ikon jam dari kartu waktu, susun informasi waktu dan batas dalam dua kolom sejajar, gunakan baris file minimum 72 px, dropzone minimum 190 px, serta tombol pengerjaan dan konfirmasi selebar konten.

Class yang direkomendasikan:

- `.assignment-header`
- `.upload-shell`
- `.upload-dropzone`
- `.selected-file`
- `.assignment-note`
- `.submission-summary`

### 6.6 Pengerjaan Quiz

- Pada detail awal desktop, kartu di bawah jadwal menggunakan dua kolom: deskripsi di kiri dan metadata `Jenis Quiz`, `Jumlah Soal`, `Durasi`, serta `Poin` di kanan dengan pemisah vertikal.
- Pada mobile, kartu informasi tersebut menjadi satu kolom dengan deskripsi selalu tampil lebih dahulu, kemudian metadata di bawahnya.
- Desktop memakai layout dua kolom dengan area soal fleksibel dan panel daftar soal sekitar `280–320px`; keseluruhan konten dibatasi sekitar `1360px` agar tidak terlalu melebar.
- Ringkasan atas memuat waktu tersisa, nomor soal, dan skor maksimal dalam card putih ringkas dengan divider vertikal.
- Pertanyaan berada dalam bordered card putih dengan radius `12px`, padding `24px`, dan shadow tipis.
- Setiap opsi jawaban berupa satu baris blue-50 dengan radio, nomor opsi, dan isi jawaban sejajar; opsi terpilih memakai navy dengan teks putih.
- Radio/checkbox asli tetap tersedia secara semantik untuk keyboard dan pembaca layar, tetapi disembunyikan secara visual; state pilihan ditunjukkan melalui perubahan warna seluruh baris opsi.
- Navigasi sebelumnya/selanjutnya berada di bawah card dan berjajar pada dua sisi.
- Panel daftar soal memakai blue-50, bersifat sticky pada desktop, dan langsung memuat grid nomor sebagai navigasi serta tombol `Selesai` tanpa legenda status atau ruang kosong buatan.
- Semua nomor soal memakai tampilan netral tanpa membedakan status dijawab dan belum dijawab; soal yang sedang dibuka tetap diberi outline sebagai konteks navigasi.
- Status respons tetap disimpan selama sesi tab untuk validasi penyelesaian, tetapi tidak mengubah warna nomor. Saat nomor atau tombol navigasi pengerjaan kuis sedang memuat, gunakan spinner putih agar tetap kontras pada desktop maupun mobile.
- Setelah selesai, tampilkan modal sukses yang memuat skor, poin diperoleh, dan tombol kembali ke detail kursus.
- Pada mobile, detail awal quiz memakai judul aktivitas 16 px yang dapat membungkus dengan lega, kartu akses tanpa ikon jam dengan informasi waktu dan batas dalam dua kolom sejajar, informasi quiz selebar konten, dan tombol mulai selebar konten. Halaman pengerjaan memakai satu kolom; ringkasan hanya menampilkan ikon jam dan waktu tersisa, perpindahan soal menggunakan tombol panah tanpa label, serta daftar nomor disembunyikan. Pada soal selain yang terakhir tampil panah sebelumnya dan berikutnya; pada soal terakhir panah berikutnya digantikan tombol `Selesai` dalam baris yang sama.
- Saat pengerjaan dimulai, gunakan focus mode tanpa topbar agar ruang vertikal diprioritaskan untuk soal; seluruh baris opsi menjadi target interaksi dengan nomor opsi dan isi jawaban sejajar.
- Pada mobile, tombol pembuka/penutup sidebar tidak dirender selama pengerjaan sehingga navigasi samping tidak dapat diakses sebelum kuis diselesaikan.
- Label bawaan Moodle seperti `Teks soal`, `Soal [nomor] Jawaban`, dan penanda opsi `A.`/`B.`/`C.`/`D.` tidak ditampilkan; tampilkan langsung isi pertanyaan dan isi setiap jawaban.
- Kontrol `Hapus pilihan saya` dari Moodle tidak ditampilkan. Saat halaman pertama kali dibuka tanpa respons valid, tidak boleh ada opsi jawaban yang terlihat terpilih.

Class yang direkomendasikan:

- `.quiz-layout`
- `.quiz-metrics`
- `.quiz-question-card`
- `.quiz-option`
- `.quiz-option.is-selected`
- `.quiz-navigation`
- `.question-index-panel`
- `.question-index-grid`

### 6.7 Detail Nilai

- Gunakan course header card yang sama dengan detail kursus.
- Panel nilai memiliki judul `Detail Nilai` dan tab underline `Tugas`/`Quiz`.
- Kolom minimum: nomor, nama aktivitas, tanggal submit, nilai, dan status.
- Nilai yang tersedia memakai success-soft; status `Dinilai` juga memakai success-soft.
- Data yang belum dinilai menggunakan `-` dan status navy `Belum Dinilai`.
- Tabel tetap sederhana tanpa shadow berat.

Class yang direkomendasikan:

- `.grade-panel`
- `.grade-tabs`
- `.grade-table`
- `.grade-value`
- `.grade-status`

### 6.8 Pencapaian dan Gamifikasi

- Header memuat ikon award biru, judul `Pencapaian Saya`, dan tombol kembali.
- Ringkasan atas dibagi dua: poin mahasiswa dan peringkat. Gunakan divider vertikal di tengah.
- Card badge menampilkan aset badge, nama, syarat pencapaian, dan status.
- Daftar badge desktop menggunakan empat kolom.
- Aksi `Selengkapnya` berada di kanan judul section.
- Leaderboard menggunakan list horizontal dengan nomor peringkat, avatar outline, nama, poin, dan ikon star.
- Peringkat 1, 2, dan 3 memiliki warna medali berbeda; baris mahasiswa aktif diberi label `Anda`.
- Leaderboard tidak menampilkan detail nilai mahasiswa lain.
- Pada mobile, gunakan skala ringkas: judul header satu baris, ikon dan tombol kembali sekitar `40px`, ringkasan poin/peringkat bertumpuk dengan padding kecil, card badge vertikal yang lebih pendek, serta baris leaderboard dan tipografi yang diperkecil tanpa mengurangi keterbacaan.

Class yang direkomendasikan:

- `.achievement-header`
- `.achievement-summary`
- `.points-summary`
- `.rank-summary`
- `.badge-grid`
- `.badge-card`
- `.leaderboard-panel`
- `.leaderboard-row`

### 6.9 Daftar Semua Badge

- Ditampilkan sebagai modal lebar di atas halaman pencapaian.
- Header memuat judul `Semua Badge` dan tombol tutup.
- Isi modal dapat di-scroll secara vertikal.
- Setiap badge berupa baris yang memuat aset badge, nama, syarat, status, dan tanggal jika sudah diperoleh.
- Status diperoleh menggunakan success-soft dengan ikon check; status belum diperoleh menggunakan surface blue-50, border blue-100, dan teks navy.
- Pada mobile, modal memakai lebar ringkas dan tinggi sekitar setengah viewport agar tidak menutupi seluruh layar. Header tetap terlihat, sedangkan daftar badge dapat di-scroll secara vertikal. Setiap card mempertahankan tinggi sesuai kontennya dan tidak boleh diperkecil untuk memaksa seluruh badge masuk sekaligus. Card memakai dua kolom stabil: aset badge ringkas di kiri serta nama, syarat, dan status di kanan; status boleh membungkus dan tidak boleh keluar atau bertumpuk dengan card berikutnya.

Class yang direkomendasikan:

- `.all-badges-modal`
- `.badge-list`
- `.badge-list-item`
- `.badge-earned-state`

### 6.10 Profil Pengguna

- Header memakai ikon profil, judul `Profil Pengguna`, deskripsi singkat pada desktop, dan tombol kembali.
- Card profil memuat identitas mahasiswa serta informasi nama lengkap dan email yang berasal dari akun aktif.
- Pada mobile, header memakai ikon dan tombol sekitar `40px` dengan judul satu baris. Avatar, nama, label peran, padding card, ikon detail, dan tipografi diperkecil secara proporsional; email panjang boleh membungkus tanpa menyebabkan overflow atau menyisakan pecahan karakter yang tidak wajar. Nama mahasiswa memakai ukuran normal selama masih satu baris; jika terdeteksi membungkus menjadi dua baris atau lebih, ukuran font diturunkan secara adaptif sampai kembali satu baris, dengan batas minimum agar tetap terbaca.

### 6.11 Notifikasi

- Halaman memuat header, ringkasan jumlah notifikasi dan batas waktu, filter `Semua`/`Batas Waktu`, serta daftar aktivitas terkait.
- Pada mobile, seluruh ikon, tipografi, padding, tombol buka, ringkasan, dan card aktivitas memakai skala ringkas. Kedua filter membagi lebar secara sama rata dan teksnya berada di tengah.
- Saat filter dipilih, tampilkan spinner pada tab yang ditekan. Spinner memakai navy pada tab terang dan putih pada tab aktif navy agar kontras.

## 7. Modal dan Feedback States

### 7.1 Modal Foundation

- Backdrop seluruh ukuran layar memakai lapisan navy transparan dengan blur sekitar `7px` agar modal tetap terpisah jelas dari konten.
- Backdrop wajib memakai `position: fixed`, `inset: 0`, lebar `100vw`, tinggi `100dvh`, dan z-index di atas topbar serta sidebar. Modal dipusatkan terhadap viewport penuh, bukan hanya area konten aktif.
- Modal kecil memiliki lebar sekitar `410–470px`; modal daftar badge sekitar `800–830px`.
- Surface putih, radius `16px`, dan shadow modal.
- Tombol tutup berada di kanan atas dengan target klik minimal `44px`, meskipun ikon visual lebih kecil.
- Fokus keyboard dikunci di dalam modal dan dikembalikan ke trigger setelah modal ditutup.
- Modal tidak boleh hanya mengandalkan blur; backdrop tetap harus membatasi interaksi halaman di belakangnya.

### 7.2 Modal Sukses Materi

- Gunakan ilustrasi reward/star pada lingkaran soft blue.
- Judul `Selamat` dan deskripsi singkat berada di tengah.
- Panel reward menampilkan ikon star dan jumlah poin, misalnya `+10 Poin`.

### 7.3 Modal Badge Baru

- Tampilkan aset badge sebagai visual utama.
- Judul `Badge Baru Diperoleh` dan deskripsi singkat berada di tengah.
- Ringkasan badge menggunakan panel blue-50 dengan badge kecil, divider, nama badge, dan syarat.

### 7.4 Modal Quiz Selesai

- Gunakan ikon sukses hijau dalam lingkaran success-soft.
- Tampilkan judul, deskripsi, skor, dan poin yang diperoleh.
- Gunakan tombol primary full-width `Kembali Ke Detail Kursus`.

### 7.5 Modal Deadline Berakhir

- Gunakan ikon alarm merah dalam lingkaran danger-soft.
- Tampilkan judul `Waktu Pengumpulan Telah Berakhir` dan penjelasan yang manusiawi.
- Panel informasi memuat batas pengumpulan dan waktu saat ini.
- Jangan tampilkan aksi submit setelah deadline berakhir.

## 8. Motion dan Interaction States

- Transisi hover/focus: `150–200ms ease`.
- Card interaktif boleh naik maksimal `1–2px`; hindari animasi besar.
- Progress bar boleh dianimasikan saat pertama tampil dengan durasi maksimal `500ms`.
- Modal memakai fade backdrop dan scale kecil dari `0.98` ke `1`.
- Hormati `prefers-reduced-motion` dengan menghilangkan transform dan animasi non-esensial.
- Loading memakai skeleton soft blue atau spinner navy; layout tidak boleh meloncat tajam.

## 9. Responsive Behavior

Desain final yang tersedia berfokus pada desktop. Aturan berikut adalah adaptasi wajib untuk ukuran lebih kecil.

### Tablet — di bawah `1024px`

- Sidebar berubah menjadi drawer atau mode collapsed.
- Area konten memakai padding `20–24px`.
- Grid course dan badge berubah menjadi dua kolom.
- Quiz layout berubah menjadi satu kolom; daftar soal berada setelah soal atau menjadi drawer.
- Hero tetap horizontal selama teks dan ilustrasi tidak bertabrakan.

### Mobile — di bawah `768px`

- Sidebar menjadi drawer overlay dan tidak mengambil lebar permanen. Pada ponsel sempit drawer memakai lebar penuh, sedangkan mobile yang lebih lebar memakai backdrop; tombol tutup tidak boleh menutupi brand dan item navigasi mempertahankan target sentuh minimal `44px`.
- Topbar tetap menyediakan tombol menu, notifikasi, dan profil serta bersifat sticky agar seluruh aksi tetap tampil sebagai satu kesatuan saat halaman digulir. Pada dashboard mobile, topbar transparan di atas hero saat posisi awal dan permukaan putih beserta shadow baru tampil setelah hero `Selamat Datang` terlewati. Pada detail kursus, permukaan putih topbar baru tampil setelah seluruh card ringkasan aktivitas terlewati.
- Login berubah menjadi satu kolom; panel brand dipadatkan di atas form dan seluruh latar halaman memakai blue-50, sementara login card tetap putih. Pada layar ponsel sempit, ukuran logo, tipografi, padding, dan jarak form dipadatkan agar card terlihat dalam satu viewport tanpa mengurangi target sentuh minimum `44px`.
- Grid course, badge, dan ringkasan berubah menjadi satu kolom.
- Hero boleh menyembunyikan atau mengecilkan ilustrasi, tetapi teks sambutan tetap tampil. Ilustrasi dan ikon halaman memakai skala mobile agar tidak mendominasi konten. Tipografi label, judul, dan deskripsi hero memakai skala mobile serta tinggi card fleksibel agar nama mahasiswa yang panjang tidak membuat konten sesak. Pada mobile, nama yang panjang ditempatkan di baris baru setelah `Selamat Datang` tanpa tanda baca dan memakai ukuran sedikit lebih kecil.
- Header halaman membungkus; status dan tombol kembali berpindah ke baris berikutnya.
- Navigasi kuis dan tombol aksi menjadi full-width jika diperlukan.
- Backdrop modal wajib memakai `position: fixed`, `inset: 0`, dan tinggi `100dvh` agar blur menutup seluruh viewport termasuk area yang sebelumnya ditempati topbar.
- Modal memakai lebar efektif maksimum sekitar `360px`, tinggi maksimum `calc(100dvh - 28px)`, padding sekitar `18–28px`, radius `12px`, dan overflow internal bila kontennya panjang. Ikon alert sekitar `88–96px`, aset badge utama sekitar `120px`, judul sekitar `19–22px`, body text sekitar `14px`, dan tombol tutup mempertahankan target sentuh `44px`.
- Tabel nilai memakai horizontal scroll dan tidak memaksa kolom menjadi terlalu sempit.
- Dropzone dan attachment panel mempertahankan target sentuh minimal `44px`.

## 10. Accessibility

- Target klik minimum `44 × 44px`.
- Semua icon-only button memiliki `aria-label`.
- Semua input memiliki label yang terhubung secara programatis.
- Focus ring terlihat jelas pada tombol, link, input, tab, accordion, dan pilihan kuis.
- Status tidak dibedakan dengan warna saja; selalu sertakan teks atau ikon.
- Kontras teks mengikuti minimal WCAG AA.
- Gunakan heading secara berurutan tanpa melompati level.
- Tab menggunakan semantics tablist/tab/tabpanel.
- Accordion mengekspos `aria-expanded` dan relasi ke panel.
- Pesan sukses dan error penting diumumkan melalui live region.
- Modal memakai `role="dialog"`, `aria-modal="true"`, judul yang terhubung, dan focus trap.
- Jangan menampilkan detail nilai pribadi mahasiswa lain di leaderboard.

## 11. Content Style

- Bahasa utama antarmuka adalah Bahasa Indonesia.
- Gunakan bahasa yang berorientasi pada tujuan mahasiswa, bukan istilah integrasi atau struktur data.
- Jangan tampilkan istilah teknis seperti `data Moodle`, `Moodle Web Service`, `endpoint`, `engine Moodle`, `token`, `request`, `module`, `assignment`, `attempt`, `final submit`, atau kode status HTTP kepada mahasiswa.
- Gunakan padanan ramah pengguna seperti `layanan pembelajaran`, `aktivitas`, `tugas`, `percobaan`, `pengumpulan akhir`, `belum dapat dimuat`, dan `silakan coba lagi`.
- Tampilkan nama lengkap kursus (`fullname`/nama tampilan). Jangan tampilkan `shortname`, ID course, ID module, atau field teknis lain di UI mahasiswa.
- Detail teknis boleh disimpan di log aplikasi untuk developer, tetapi tidak boleh dimasukkan ke alert, empty state, helper text, modal, atau toast mahasiswa.
- Gunakan istilah yang sama dengan Information Architecture:
  - `Dashboard`
  - `Kursus Pembelajaran`
  - `Daftar Kursus`
  - `Detail Kursus`
  - `Aktivitas Pembelajaran`
  - `Pencapaian`
  - `Nilai`
  - `Notifikasi`
  - `Profil Pengguna`
  - `Logout` sebagai label aksi keluar sesuai desain final.
- Gunakan `Kursus`, bukan campuran `Course`, pada teks yang dilihat mahasiswa.
- Gunakan `Quiz` sebagai label aktivitas pada antarmuka sesuai desain final. Istilah `kuis` tetap dapat digunakan dalam dokumentasi teknis, tetapi jangan mencampurkan keduanya dalam satu tampilan.
- Gunakan kalimat ringkas, langsung, dan suportif.
- Pesan error menjelaskan masalah dan akibatnya tanpa menyalahkan mahasiswa.
- Jika integrasi gagal, gunakan pesan seperti `Layanan pembelajaran sedang mengalami gangguan. Silakan coba lagi beberapa saat.` tanpa meneruskan exception mentah.

## 12. Assets dan Illustration

- Gunakan ilustrasi dashboard dengan gaya flat, dominan biru, dan background transparan.
- Aset badge boleh lebih detail dan penuh warna dibanding ikon UI biasa.
- Badge harus tersedia dalam ukuran besar untuk modal dan ukuran kecil untuk card/list tanpa kehilangan keterbacaan.
- Ikon UI tetap menggunakan Lucide; ilustrasi dan aset badge tidak diganti dengan Lucide.
- Semua aset bermakna memiliki alternative text; aset dekoratif menggunakan alt kosong.

## 13. Implementation Notes

- Source of truth implementasi style tetap berada di `resources/css/app.css`.
- Gunakan CSS custom properties untuk token warna, spacing, radius, dan shadow pada dokumen ini.
- Tailwind CSS v4 digunakan melalui `@import "tailwindcss"` jika implementasi tetap memakai pipeline saat ini.
- Instrument Sans harus dimuat secara eksplisit dan diterapkan pada root aplikasi serta form controls.
- Gunakan class komponen yang konsisten; nama class rekomendasi dalam dokumen ini dapat dipetakan ke class existing selama hasil visualnya sama.
- Data akademik, progress, nilai, deadline, dan gamifikasi tetap berasal dari Moodle Web Service sesuai SRS; desain tidak mengubah source of truth data.
- Jangan menambah layout atau fitur khusus Dosen.
- Screenshot final adalah acuan untuk proporsi desktop; token dokumen ini menjadi acuan ketika ukuran viewport atau konten berubah.

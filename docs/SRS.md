# Software Requirements Specification (SRS)
# E-Learning Lite Berbasis Gamifikasi

**Versi Dokumen:** 1.6
**Status:** Draft revisi pengalaman UI mahasiswa
**Catatan:** Dokumen ini menetapkan Mahasiswa sebagai satu-satunya aktor E-Learning Lite. Sistem merupakan antarmuka alternatif yang lebih sederhana untuk E-Learning UAD/Moodle dengan tambahan feedback gamifikasi. Sistem menggunakan akun E-Learning UAD/Moodle melalui Moodle Web Service untuk mengakses data dan aktivitas pembelajaran mahasiswa. Data akademik utama tetap bersumber dari Moodle, sedangkan completion gamifikasi E-Learning Lite disimpan secara lokal agar poin dan leaderboard dapat dibaca lintas akun.

---

## Revision History

| Name | Date | Reason for Changes | Version |
|---|---|---|---|
| Penyusun SRS | 23 Mei 2026 | Penyusunan awal dokumen SRS berdasarkan hasil wawancara kebutuhan sistem | 1.0 |
| Penyusun SRS | 23 Mei 2026 | Revisi kebutuhan integrasi kursus | 1.1 |
| Penyusun SRS | 5 Juni 2026 | Revisi source of truth berdasarkan hasil diskusi 3 Juni 2026: integrasi Moodle sebagai sumber dan tujuan data pembelajaran, login melalui Moodle Web Service, dan gamifikasi dihitung dari data aktivitas Moodle | 1.2 |
| Penyusun SRS | 18 Agustus 2026 | Revisi ruang lingkup sistem sehingga Mahasiswa menjadi satu-satunya aktor; fitur Dosen dan kemampuan pengelolaan khusus Dosen dikeluarkan dari ruang lingkup | 1.3 |
| Penyusun SRS | 18 Agustus 2026 | Revisi copy UI agar ramah mahasiswa, menyembunyikan field teknis seperti shortname/ID, dan menghapus navigasi fallback ke E-Learning UAD/Moodle | 1.4 |
| Penyusun SRS | 19 Agustus 2026 | Menambahkan fallback status penyelesaian khusus E-Learning Lite untuk materi/resource yang tidak menyediakan activity completion Moodle | 1.5 |
| Penyusun SRS | 25 Agustus 2026 | Menetapkan penyimpanan completion gamifikasi lintas akun di SQLite, aturan tetap 10 poin per aktivitas, dan menghapus bonus poin | 1.6 |

---

## Table of Contents

- [1. Introduction](#1-introduction)
  - [1.1 Purpose](#11-purpose)
  - [1.2 Document Conventions](#12-document-conventions)
  - [1.3 Intended Audience and Reading Suggestions](#13-intended-audience-and-reading-suggestions)
  - [1.4 Project Scope](#14-project-scope)
  - [1.5 References](#15-references)
- [2. Overall Description](#2-overall-description)
  - [2.1 Product Perspective](#21-product-perspective)
  - [2.2 Product Features](#22-product-features)
  - [2.3 User Classes and Characteristics](#23-user-classes-and-characteristics)
  - [2.4 Operating Environment](#24-operating-environment)
  - [2.5 Design and Implementation Constraints](#25-design-and-implementation-constraints)
  - [2.6 User Documentation](#26-user-documentation)
  - [2.7 Assumptions and Dependencies](#27-assumptions-and-dependencies)
- [3. System Features](#3-system-features)
- [4. External Interface Requirements](#4-external-interface-requirements)
- [5. Other Nonfunctional Requirements](#5-other-nonfunctional-requirements)
- [6. Other Requirements](#6-other-requirements)
- [Appendix A: Glossary](#appendix-a-glossary)
- [Appendix B: Analysis Models](#appendix-b-analysis-models)
- [Appendix C: Issues List](#appendix-c-issues-list)

---

# 1. Introduction

## 1.1 Purpose

Dokumen Software Requirements Specification (SRS) ini dibuat untuk mendefinisikan kebutuhan perangkat lunak **E-Learning Lite Berbasis Gamifikasi**. Sistem ini dikembangkan sebagai prototype/skripsi untuk menyediakan antarmuka pembelajaran yang lebih sederhana terhadap data pembelajaran yang sudah tersedia pada **E-Learning UAD/Moodle**.

SRS ini menjadi acuan utama atau source of truth untuk analisis kebutuhan, rancangan, implementasi, pengujian, dan evaluasi sistem. Dokumen ini menegaskan bahwa data akademik utama seperti kursus, peserta, materi, kuis, tugas, nilai, dan progress pembelajaran berasal dari E-Learning UAD melalui Moodle Web Service. E-Learning Lite tidak dirancang sebagai LMS pengganti dan tidak menyimpan ulang data akademik utama sebagai sumber data baru.

Fokus sistem adalah:

1. Menyediakan antarmuka yang lebih ringan dan mudah dipahami untuk mengakses data pembelajaran dari E-Learning UAD.
2. Menambahkan elemen gamifikasi untuk meningkatkan motivasi mahasiswa.
3. Menyediakan antarmuka bagi mahasiswa untuk mengakses dan mengikuti aktivitas pembelajaran tertentu melalui Moodle Web Service.
4. Menghitung feedback gamifikasi dari aktivitas pembelajaran dan menyimpan completion gamifikasi E-Learning Lite secara lokal untuk menjamin konsistensi poin serta leaderboard lintas akun, tanpa menjadikannya sebagai data akademik Moodle.

## 1.2 Document Conventions

Konvensi penulisan dokumen:

1. Setiap kebutuhan fungsional diberi nomor unik dengan format **REQ-001**, **REQ-002**, dan seterusnya.
2. Setiap kebutuhan nonfungsional diberi nomor unik dengan format **NFR-001**, **NFR-002**, dan seterusnya.
3. Prioritas kebutuhan dibagi menjadi:
   - **High**: kebutuhan utama yang wajib tersedia.
   - **Medium**: kebutuhan pendukung yang penting.
   - **Low**: kebutuhan tambahan atau pengembangan lanjutan.
4. Istilah **sistem** mengacu pada E-Learning Lite Berbasis Gamifikasi.
5. Istilah **E-Learning UAD** mengacu pada LMS berbasis Moodle yang menjadi sumber data pembelajaran.
6. Istilah **Moodle Web Service** mengacu pada mekanisme integrasi yang digunakan E-Learning Lite untuk autentikasi, pengambilan data, dan pengiriman data pembelajaran ke E-Learning UAD.
7. Kata **harus** menunjukkan kebutuhan yang wajib dipenuhi.
8. Kata **dapat** menunjukkan kemampuan sistem sesuai batasan akses dan data yang tersedia.

## 1.3 Intended Audience and Reading Suggestions

Dokumen ini ditujukan untuk:

1. **Dosen pembimbing**, sebagai pihak yang menilai kesesuaian kebutuhan sistem dengan tujuan penelitian.
2. **Penguji**, sebagai pihak yang mengevaluasi kelengkapan analisis kebutuhan.
3. **Developer/programmer**, sebagai acuan implementasi sistem.
4. **Mahasiswa sebagai pengguna**, untuk memahami fitur akses pembelajaran dan gamifikasi.

Saran pembacaan:

- Bagian **Introduction** menjelaskan tujuan dan ruang lingkup.
- Bagian **Overall Description** menjelaskan konteks sistem dan batasan utama.
- Bagian **System Features** memuat kebutuhan fungsional.
- Bagian **External Interface Requirements** menjelaskan antarmuka dan integrasi Moodle.
- Bagian **Nonfunctional Requirements** menjelaskan aspek kualitas sistem.
- Bagian **Appendix** memuat istilah dan isu terbuka.

## 1.4 Project Scope

**E-Learning Lite Berbasis Gamifikasi** adalah aplikasi web prototype yang berfungsi sebagai antarmuka alternatif untuk E-Learning UAD/Moodle. Sistem ini tidak menggantikan E-Learning UAD, melainkan mengambil data pembelajaran melalui Moodle Web Service dan menampilkannya dalam bentuk yang lebih sederhana, ringkas, dan berorientasi pengalaman pengguna.

Ruang lingkup sistem:

1. Menyediakan login menggunakan akun E-Learning UAD/Moodle melalui Moodle Web Service.
2. Menampilkan daftar kursus yang diikuti mahasiswa sesuai data dari E-Learning UAD.
3. Menampilkan informasi pembelajaran mahasiswa seperti materi, kuis/tugas, nilai, progress, dan pencapaian apabila data tersebut tersedia melalui Moodle Web Service.
4. Menyediakan antarmuka bagi mahasiswa untuk mengakses materi/resource, mengikuti kuis, mengumpulkan tugas, serta melihat nilai, progress, dan pencapaian yang tersedia dari Moodle sesuai layanan Moodle Web Service.
5. Menyediakan dashboard ringkas untuk Mahasiswa.
6. Menambahkan feedback gamifikasi berupa poin, badge/lencana, leaderboard, dan progress bar berdasarkan aktivitas pembelajaran, dengan bobot tetap 10 poin untuk setiap aktivitas yang selesai dan tanpa bonus poin.
7. Menggunakan SQLite untuk menyimpan completion gamifikasi E-Learning Lite serta file session/cache untuk kebutuhan teknis prototype; completion lokal bukan data akademik Moodle.

Di luar ruang lingkup utama:

1. Sistem tidak menjadi LMS lokal pengganti Moodle.
2. Sistem tidak menyimpan data akademik utama seperti course, materi, kuis, tugas, nilai, dosen, dan mahasiswa sebagai sumber data baru.
3. Pembuatan kursus baru dari E-Learning Lite tidak termasuk ruang lingkup karena service Moodle yang digunakan saat ini, yaitu `moodle-mobile-app`, tidak menyediakan fitur pembuatan kursus.
4. Pengingat di luar aplikasi, seperti email, SMS, atau push notification, tidak termasuk ruang lingkup prototype.
5. Fitur Dosen, termasuk pengelolaan course, materi, kuis/tugas, penilaian, peserta, dan monitoring mahasiswa, tidak termasuk ruang lingkup E-Learning Lite.

## 1.5 References

Referensi:

1. E-Learning UAD/Moodle sebagai sumber data pembelajaran.
2. Dokumentasi Moodle Web Service.
3. Project E-Learning Lite berbasis Laravel yang sedang berjalan.

---

# 2. Overall Description

## 2.1 Product Perspective

E-Learning Lite merupakan aplikasi web pendukung yang berkomunikasi dengan E-Learning UAD/Moodle melalui Moodle Web Service. Sistem ini mengambil data akademik utama dari Moodle dan menyajikannya dalam antarmuka yang lebih sederhana. Dengan demikian, E-Learning UAD tetap menjadi sistem utama, sedangkan E-Learning Lite menjadi layer antarmuka dan feedback gamifikasi.

Posisi sistem:

1. **E-Learning UAD/Moodle** menjadi sumber data utama untuk kursus, peserta, materi, kuis/tugas, nilai, dan progress akademik.
2. **Moodle Web Service** digunakan untuk autentikasi akun Moodle, pengambilan data, dan pengiriman data pembelajaran sesuai fitur yang tersedia.
3. **E-Learning Lite** menyediakan tampilan pembelajaran mahasiswa yang lebih sederhana, dashboard ringkas, pencarian, akses aktivitas pembelajaran, progress, pencapaian, nilai, notifikasi, dan feedback gamifikasi.
4. **Penyimpanan lokal E-Learning Lite** digunakan untuk session, cache, dan completion gamifikasi per mahasiswa, mata kuliah, serta aktivitas. Penyimpanan ini menjadi sumber perhitungan poin dan leaderboard E-Learning Lite, tetapi tidak menggantikan data akademik maupun completion resmi Moodle.

Integrasi dengan Moodle dilakukan melalui Moodle Web Service untuk membaca data pembelajaran dan mengirim hasil aktivitas mahasiswa sesuai hak akses, konfigurasi E-Learning UAD/Moodle, dan layanan Moodle Web Service yang tersedia. Data akademik utama tetap berada pada E-Learning UAD/Moodle, sedangkan E-Learning Lite berperan sebagai antarmuka yang lebih sederhana bagi mahasiswa untuk mengakses pembelajaran.

## 2.2 Product Features

Fitur utama sistem:

1. Login menggunakan akun E-Learning UAD/Moodle melalui Moodle Web Service.
2. Dashboard Mahasiswa.
3. Menampilkan daftar kursus dari E-Learning UAD sesuai akun mahasiswa.
4. Menampilkan detail kursus yang tersedia melalui Moodle Web Service.
5. Mengakses materi pembelajaran/resource course dari Moodle dan membuka detailnya melalui E-Learning Lite.
6. Menampilkan struktur/topik dan isi course yang dapat diakses mahasiswa dari Moodle.
7. Mengakses kuis dan informasi aktivitas pembelajaran dari Moodle.
8. Mengakses dan mengumpulkan tugas sesuai hak akses mahasiswa dan layanan Moodle Web Service yang tersedia.
9. Menampilkan nilai, progress, dan pencapaian mahasiswa apabila data tersedia dari Moodle Web Service.
10. Menyediakan pencarian/filter data kursus dan aktivitas pembelajaran.
11. Menampilkan progress belajar dalam bentuk yang lebih mudah dibaca.
12. Menyediakan feedback gamifikasi berupa 10 poin untuk setiap aktivitas yang selesai, badge/lencana, dan leaderboard per kursus tanpa bonus poin.
13. Menampilkan pengingat deadline pada dashboard berdasarkan data Moodle apabila tersedia.
14. Menampilkan pesan kegagalan jika Moodle Web Service tidak dapat diakses atau tidak mendukung aksi tertentu.
15. Menampilkan daftar notifikasi pembelajaran apabila data tersedia dari Moodle.
16. Menampilkan informasi profil mahasiswa dan menyediakan fungsi keluar dari sistem.
17. Menyediakan seluruh alur pembelajaran dalam E-Learning Lite tanpa tombol fallback yang mengarahkan mahasiswa ke E-Learning UAD/Moodle.

## 2.3 User Classes and Characteristics

### 2.3.1 Mahasiswa

Mahasiswa adalah pengguna yang mengikuti kursus pada E-Learning UAD. Dalam E-Learning Lite, Mahasiswa menggunakan sistem untuk mengakses kursus, materi, kuis/tugas, nilai, progress, dan elemen gamifikasi.

Karakteristik Mahasiswa:

- Memiliki akses ke kursus yang diikuti pada E-Learning UAD.
- Membutuhkan tampilan pembelajaran yang sederhana dan mudah dipahami.
- Membutuhkan informasi progress, deadline, nilai, dan aktivitas pembelajaran.
- Mendapatkan poin, badge, dan posisi leaderboard berdasarkan aktivitas pembelajaran.

## 2.4 Operating Environment

Lingkungan operasional sistem:

1. Sistem berbentuk website.
2. Sistem utamanya digunakan melalui laptop/PC, sedangkan akses melalui HP hanya sebagai tambahan.
3. Sistem dikembangkan sebagai local development/prototype pada laptop/PC developer.
4. Sistem operasi pengembangan utama adalah Windows.
5. Sistem dapat diakses melalui browser modern:
   - Google Chrome
   - Mozilla Firefox
   - Microsoft Edge
   - Safari
6. Teknologi pengembangan:
   - Laravel 12 sebagai framework utama.
   - Blade sebagai template antarmuka.
   - Vite sebagai build tool.
   - Tailwind CSS v4 untuk styling antarmuka.
   - SQLite untuk completion gamifikasi E-Learning Lite serta file untuk session/cache pada setup default.
   - Moodle Web Service untuk autentikasi dan pengambilan data dari E-Learning UAD.
7. Sistem membutuhkan koneksi internet untuk melakukan autentikasi melalui E-Learning UAD/Moodle dan mengambil data pembelajaran melalui Moodle Web Service.

## 2.5 Design and Implementation Constraints

Batasan desain dan implementasi:

1. Sistem dikembangkan menggunakan Laravel 12, Blade, Vite, Tailwind CSS v4, dan Moodle Web Service.
2. Sistem hanya mendukung satu aktor, yaitu Mahasiswa.
3. Dosen dan Admin tidak termasuk sebagai aktor E-Learning Lite pada ruang lingkup SRS ini.
4. Login menggunakan akun E-Learning UAD/Moodle melalui Moodle Web Service.
5. Integrasi Moodle digunakan untuk membaca data pembelajaran dan mengirim hasil aktivitas sesuai hak akses mahasiswa, konfigurasi E-Learning UAD/Moodle, dan layanan Moodle Web Service yang tersedia.
6. Service Moodle yang digunakan saat ini adalah `moodle-mobile-app`, sehingga pembuatan kursus baru dari E-Learning Lite tidak didukung.
7. Sistem tidak menyimpan data akademik utama sebagai sumber data lokal baru.
8. SQLite digunakan untuk menyimpan completion gamifikasi E-Learning Lite secara unik per mahasiswa, mata kuliah, dan aktivitas agar poin dapat dibaca lintas session dan lintas akun.
9. Data akademik utama tetap tersimpan pada E-Learning UAD/Moodle, sedangkan E-Learning Lite menjadi antarmuka bagi mahasiswa untuk mengakses data dan mengikuti aktivitas sesuai fitur integrasi.
10. Sistem dikembangkan sebagai prototype/skripsi.
11. Bahasa antarmuka sistem menggunakan Bahasa Indonesia.
12. Sistem berbentuk website dan tidak mencakup aplikasi mobile native.

## 2.6 User Documentation

Sistem menyediakan panduan singkat di dalam sistem, berupa:

1. Informasi singkat pada dashboard.
2. Tooltip atau bantuan pada fitur gamifikasi.
3. Bantuan singkat untuk Mahasiswa dalam memahami aktivitas pembelajaran, progress, pencapaian, nilai, poin, badge, dan leaderboard.

## 2.7 Assumptions and Dependencies

Asumsi dan dependensi:

1. Mahasiswa memiliki akun E-Learning UAD/Moodle yang valid.
2. Moodle Web Service tersedia dan dapat diakses oleh E-Learning Lite.
3. Data course, materi, kuis/tugas, nilai, progress, dan pencapaian mahasiswa tersedia melalui Moodle Web Service sesuai fitur integrasi.
4. Identitas mahasiswa dan akses kursus dapat ditentukan dari data Moodle yang tersedia.
5. Data peserta dan struktur aktivitas bergantung pada Moodle Web Service; completion gamifikasi yang sudah tercatat di E-Learning Lite tetap dapat digunakan saat completion mahasiswa lain tidak dapat dibaca dari Moodle.
6. Ketersediaan akses materi/resource, kuis, tugas, section/topik, dan informasi dasar course bergantung pada layanan Moodle Web Service yang tersedia.
7. Service `moodle-mobile-app` tidak menyediakan fitur pembuatan kursus, sehingga kursus baru dibuat melalui E-Learning UAD/Moodle dan kemudian dapat ditampilkan di E-Learning Lite.
8. Jika Moodle Web Service bermasalah atau tidak mendukung aksi akademik, sistem menampilkan pesan kegagalan dan tidak menyimpan data akademik tersebut secara lokal sebagai pengganti. Khusus materi/resource tanpa activity completion Moodle, sistem dapat menyimpan status penyelesaian antarmuka E-Learning Lite tanpa menganggapnya sebagai completion akademik Moodle.
9. Setiap aktivitas yang selesai bernilai 10 poin, tidak ada bonus poin, dan satu aktivitas hanya boleh dihitung satu kali untuk setiap mahasiswa pada mata kuliah yang sama.

---

# 3. System Features

## 3.1 Autentikasi dan Hak Akses

### 3.1.1 Description and Priority

Fitur ini memungkinkan mahasiswa masuk ke E-Learning Lite menggunakan akun E-Learning UAD/Moodle melalui Moodle Web Service. Setelah login, sistem menampilkan data sesuai akses mahasiswa pada Moodle. Fitur ini memiliki prioritas **High**.

### 3.1.2 Stimulus/Response Sequences

1. Mahasiswa membuka halaman login E-Learning Lite.
2. Mahasiswa memasukkan username dan password akun E-Learning UAD/Moodle.
3. Sistem melakukan autentikasi ke Moodle Web Service.
4. Jika berhasil, sistem mengambil data mahasiswa dan course yang diikuti.
5. Sistem mengarahkan mahasiswa ke dashboard.
6. Jika gagal, sistem menampilkan pesan login gagal.

### 3.1.3 Functional Requirements

| ID | Requirement | Priority |
|---|---|---|
| REQ-001 | Sistem harus menyediakan login menggunakan akun E-Learning UAD/Moodle melalui Moodle Web Service. | High |
| REQ-002 | Sistem harus menolak akses pengguna yang tidak berhasil diautentikasi melalui Moodle. | High |
| REQ-003 | Sistem harus mengelola sesi autentikasi mahasiswa secara aman selama sesi aktif. | High |
| REQ-004 | Sistem harus menampilkan data sesuai course yang diikuti mahasiswa pada Moodle. | High |
| REQ-005 | Sistem harus menyediakan fungsi keluar untuk mengakhiri sesi mahasiswa. | High |
| REQ-006 | Sistem harus mencegah akses ke halaman yang membutuhkan login tanpa sesi yang valid. | High |

## 3.2 Integrasi Data E-Learning UAD

### 3.2.1 Description and Priority

Fitur ini memungkinkan sistem berkomunikasi dengan E-Learning UAD melalui Moodle Web Service untuk mengambil data pembelajaran dan mengirim hasil aktivitas sesuai hak akses mahasiswa, konfigurasi E-Learning UAD/Moodle, dan layanan yang tersedia. Fitur ini memiliki prioritas **High**.

### 3.2.2 Stimulus/Response Sequences

1. Mahasiswa berhasil login.
2. Sistem mengambil data mahasiswa dari Moodle.
3. Sistem mengambil daftar course yang diikuti mahasiswa.
4. Sistem mengambil data pendukung seperti nilai, materi, kuis/tugas, progress, dan pencapaian apabila data tersedia.
5. Sistem menampilkan data dalam antarmuka E-Learning Lite.
6. Ketika mahasiswa menyelesaikan atau mengumpulkan aktivitas melalui Lite, sistem mengirim hasil aktivitas tersebut ke E-Learning UAD/Moodle sesuai hak akses mahasiswa, konfigurasi E-Learning UAD/Moodle, dan layanan Moodle Web Service yang tersedia.
7. Jika Moodle Web Service gagal, sistem menampilkan pesan kegagalan.

### 3.2.3 Functional Requirements

| ID | Requirement | Priority |
|---|---|---|
| REQ-007 | Sistem harus mengambil data mahasiswa dari E-Learning UAD melalui Moodle Web Service. | High |
| REQ-008 | Sistem harus mengambil daftar course yang diikuti mahasiswa dari E-Learning UAD. | High |
| REQ-009 | Sistem harus mengambil informasi profil mahasiswa apabila data tersedia. | Medium |
| REQ-010 | Sistem harus mengambil data nilai apabila data tersedia. | Medium |
| REQ-011 | Sistem harus mengambil data materi/resource, kuis/tugas, dan progress apabila data tersedia. | Medium |
| REQ-012 | Sistem harus mengirim hasil aktivitas mahasiswa yang didukung oleh Moodle Web Service, seperti submission tugas, ke E-Learning UAD/Moodle sesuai hak akses mahasiswa dan konfigurasi Moodle. | High |
| REQ-013 | Sistem harus menampilkan pesan yang mudah dipahami jika Moodle Web Service gagal diakses atau tidak mendukung aksi tertentu. | Medium |
| REQ-014 | Sistem tidak boleh menyimpan ulang data akademik utama sebagai sumber data lokal pengganti Moodle. | High |

## 3.3 Dashboard

### 3.3.1 Description and Priority

Dashboard menyediakan ringkasan pembelajaran yang mudah dipahami oleh Mahasiswa. Fitur ini memiliki prioritas **High**.

### 3.3.2 Functional Requirements

| ID | Requirement | Priority |
|---|---|---|
| REQ-015 | Sistem harus menampilkan daftar course yang diikuti mahasiswa. | High |
| REQ-016 | Sistem harus menampilkan ringkasan aktivitas pembelajaran berdasarkan data Moodle yang tersedia. | Medium |
| REQ-017 | Sistem harus menampilkan informasi progress dan gamifikasi pada dashboard Mahasiswa. | Medium |
| REQ-018 | Sistem harus menampilkan ringkasan notifikasi dan deadline aktivitas yang belum diselesaikan pada dashboard apabila data tersedia. | Medium |
| REQ-019 | Sistem harus menyediakan pencarian course pada dashboard atau daftar course. | Medium |

## 3.4 Course dan Detail Pembelajaran

### 3.4.1 Description and Priority

Fitur ini memungkinkan mahasiswa melihat detail course yang berasal dari Moodle, termasuk aktivitas pembelajaran, materi/resource, kuis, tugas, pencapaian, progress, dan nilai apabila data tersedia melalui Moodle Web Service. Fitur ini memiliki prioritas **High**.

### 3.4.2 Functional Requirements

| ID | Requirement | Priority |
|---|---|---|
| REQ-020 | Sistem harus menampilkan daftar course dari Moodle sesuai akses mahasiswa. | High |
| REQ-021 | Sistem tidak menyediakan pembuatan kursus baru dari E-Learning Lite karena service `moodle-mobile-app` tidak mendukung fitur tersebut; kursus baru dibuat melalui E-Learning UAD/Moodle. | High |
| REQ-022 | Sistem harus menampilkan detail course berdasarkan data Moodle yang tersedia. | High |
| REQ-023 | Sistem harus menyediakan fitur untuk mengakses dan menampilkan materi pembelajaran/resource course dari Moodle. | High |
| REQ-023A | Detail materi harus menampilkan deskripsi resource yang tersedia melalui Moodle Web Service, dengan data ringkas modul sebagai fallback jika detail resource tidak tersedia. | High |
| REQ-024 | Sistem harus menyediakan fitur untuk mengakses kuis dan informasi aktivitas pembelajaran dari Moodle. | High |
| REQ-025 | Sistem harus menyediakan fitur akses dan pengumpulan tugas melalui E-Learning Lite apabila layanan Moodle Web Service mendukung dan mahasiswa memiliki hak akses yang sesuai. | High |
| REQ-025A | Detail tugas harus menampilkan deskripsi assignment, kemudian instruksi assignment yang diikuti panduan pengumpulan tambahan dari aplikasi, dengan judul antarmuka `Deskripsi Tugas` dan `Instruksi Tugas`. | High |
| REQ-025B | Detail tugas harus menampilkan berkas tambahan assignment yang tersedia dan diizinkan bagi mahasiswa oleh E-Learning UAD/Moodle pada bagian Lampiran. | High |
| REQ-026 | Sistem harus menampilkan detail nilai tugas hanya setelah tugas dikumpulkan dan detail nilai kuis hanya setelah kuis dikerjakan. Tugas atau kuis yang telah diselesaikan tetapi belum memiliki nilai tetap ditampilkan dengan status `Belum Dinilai`, sedangkan aktivitas yang belum dikumpulkan atau belum dikerjakan tidak ditampilkan. | Medium |
| REQ-027 | Sistem harus menampilkan isi course yang dapat diakses mahasiswa, seperti topik/section, materi/aktivitas, dan informasi dasar course dari Moodle. | Medium |
| REQ-027A | Jika materi/resource tidak menyediakan activity completion Moodle, tombol Selesai harus mencatat penyelesaian khusus E-Learning Lite agar progress dan feedback gamifikasi tetap dapat digunakan, tanpa mengubah atau mengklaim status completion Moodle. | High |

## 3.5 Pencapaian Mahasiswa

### 3.5.1 Description and Priority

Fitur ini membantu mahasiswa melihat ringkasan aktivitas, progress, pencapaian, dan posisinya pada leaderboard. Daftar peserta tetap berasal dari Moodle, sedangkan poin leaderboard dibaca dari completion gamifikasi E-Learning Lite yang tersimpan per mata kuliah. Fitur ini memiliki prioritas **Medium**.

### 3.5.2 Functional Requirements

| ID | Requirement | Priority |
|---|---|---|
| REQ-028 | Sistem harus menampilkan ringkasan aktivitas pembelajaran mahasiswa apabila data tersedia. | Medium |
| REQ-029 | Sistem harus menampilkan progress mahasiswa jika data progress tersedia. | Medium |
| REQ-030 | Sistem harus menampilkan pencapaian mahasiswa berdasarkan aktivitas pembelajaran yang tersedia. | Medium |
| REQ-031 | Sistem harus menampilkan leaderboard gamifikasi per course untuk seluruh mahasiswa terdaftar yang dapat dibaca dari Moodle, termasuk poin E-Learning Lite yang telah tersimpan lintas akun. | Medium |
| REQ-032 | Sistem tidak boleh menampilkan detail nilai pribadi mahasiswa kepada mahasiswa lain. | High |

## 3.6 Gamifikasi Pembelajaran

### 3.6.1 Description and Priority

Fitur ini menyediakan feedback gamifikasi untuk meningkatkan motivasi mahasiswa. Setiap aktivitas yang selesai bernilai tetap 10 poin dan tidak ada bonus poin. Completion gamifikasi dicatat secara lokal per mahasiswa, mata kuliah, dan aktivitas agar satu aktivitas tidak dihitung ganda serta leaderboard dapat dibaca lintas akun. Pencatatan tersebut tidak mengubah data akademik atau completion resmi Moodle. Fitur ini memiliki prioritas **Medium**.

### 3.6.2 Stimulus/Response Sequences

1. Mahasiswa mengakses atau menyelesaikan aktivitas pembelajaran pada course.
2. Sistem membaca data aktivitas/progress dari Moodle apabila tersedia dan menggabungkannya dengan completion gamifikasi E-Learning Lite.
3. Sistem mencatat completion secara unik dan memberikan 10 poin untuk aktivitas yang baru selesai.
4. Sistem memberikan badge/lencana jika syarat terpenuhi.
5. Sistem memperbarui leaderboard per course.
6. Mahasiswa melihat poin, badge, progress, dan posisi leaderboard.

### 3.6.3 Functional Requirements

| ID | Requirement | Priority |
|---|---|---|
| REQ-033 | Sistem harus memberikan tepat 10 poin untuk setiap aktivitas pembelajaran yang selesai dan tercatat pada E-Learning Lite. | Medium |
| REQ-034 | Sistem harus menyediakan badge/lencana berdasarkan pencapaian tertentu. | Medium |
| REQ-035 | Sistem harus menyediakan leaderboard per course berdasarkan akumulasi completion aktivitas unik seluruh mahasiswa yang telah tercatat pada E-Learning Lite. | Medium |
| REQ-036 | Sistem harus menyediakan progress bar pembelajaran. | Medium |
| REQ-037 | Sistem tidak memberikan bonus poin; seluruh aktivitas yang selesai memiliki bobot yang sama, yaitu 10 poin. | Medium |
| REQ-038 | Sistem harus menyimpan completion gamifikasi secara lokal tanpa mengubah atau mengklaimnya sebagai completion akademik Moodle. | High |
| REQ-038A | Sistem harus mencegah satu aktivitas yang sama memberikan poin lebih dari satu kali kepada mahasiswa pada course yang sama. | High |
| REQ-039 | Leaderboard tidak boleh menampilkan detail nilai pribadi mahasiswa lain. | High |
| REQ-040 | Sistem harus memungkinkan aturan gamifikasi disesuaikan pada tahap implementasi. | Low |

## 3.7 Notifikasi dan Deadline

### 3.7.1 Description and Priority

Fitur ini menampilkan daftar notifikasi dan informasi deadline berdasarkan data Moodle apabila tersedia. Fitur ini memiliki prioritas **Medium**.

### 3.7.2 Functional Requirements

| ID | Requirement | Priority |
|---|---|---|
| REQ-041 | Sistem harus menampilkan daftar notifikasi pembelajaran apabila data tersedia dari Moodle. | Medium |
| REQ-042 | Sistem harus menampilkan deadline kuis/tugas dan aktivitas yang mendekati deadline secara ringkas apabila data tersedia. | Medium |
| REQ-043 | Sistem harus menampilkan notifikasi dan pengingat deadline sebagai informasi di dalam aplikasi. | Medium |

## 3.8 Pencarian dan Filter

### 3.8.1 Description and Priority

Fitur ini membantu pengguna menemukan course dan informasi pembelajaran dengan lebih cepat. Fitur ini memiliki prioritas **Medium**.

### 3.8.2 Functional Requirements

| ID | Requirement | Priority |
|---|---|---|
| REQ-044 | Sistem harus menyediakan pencarian course. | Medium |
| REQ-045 | Sistem dapat menyediakan filter berdasarkan course, status, atau deadline apabila data tersedia. | Low |
| REQ-046 | Sistem harus menjaga input pencarian sebagai teks biasa dan tidak merender HTML mentah dari input pengguna. | High |

## 3.9 Terminologi dan Navigasi Internal

### 3.9.1 Description and Priority

Fitur ini memastikan mahasiswa menerima informasi yang mudah dipahami dan menyelesaikan flow utama tanpa diarahkan ke antarmuka teknis atau platform sumber. Fitur ini memiliki prioritas **High**.

### 3.9.2 Functional Requirements

| ID | Requirement | Priority |
|---|---|---|
| REQ-047 | Sistem harus menggunakan teks ramah pengguna dan tidak menampilkan istilah integrasi seperti `data Moodle`, `Moodle Web Service`, endpoint, token, request, atau pesan exception mentah pada UI mahasiswa. | High |
| REQ-048 | Sistem harus menampilkan nama lengkap kursus dan tidak menampilkan shortname, ID course, ID module, atau field teknis lain pada UI mahasiswa. | High |
| REQ-049 | Sistem tidak boleh menampilkan tombol atau tautan fallback untuk membuka aktivitas di E-Learning UAD/Moodle; seluruh flow yang didukung harus dijalankan di E-Learning Lite. | High |
| REQ-050 | Jika suatu fungsi belum tersedia, sistem harus menampilkan pesan yang mudah dipahami tanpa mengarahkan mahasiswa ke platform sumber. | High |

---

# 4. External Interface Requirements

## 4.1 User Interfaces

Antarmuka sistem menggunakan gaya dashboard yang ringan, bersih, dan mudah dipahami. Karakteristik antarmuka:

1. Sidebar navigasi untuk menu utama.
2. Dashboard ringkas untuk informasi course, progress, deadline, dan feedback gamifikasi.
3. Search bar untuk pencarian course.
4. Card atau panel untuk menampilkan ringkasan data.
5. Bahasa antarmuka menggunakan Bahasa Indonesia.
6. Tampilan dioptimalkan untuk laptop/PC.
7. Label dan pesan menggunakan istilah yang dipahami mahasiswa serta tidak menampilkan field atau istilah teknis integrasi.
8. Navigasi aktivitas pembelajaran tetap berada di E-Learning Lite tanpa tombol fallback ke antarmuka E-Learning UAD/Moodle.

### 4.1.1 Menu Mahasiswa

Menu utama Mahasiswa:

1. **Dashboard**: halaman utama setelah login yang menyediakan akses ke kursus pembelajaran, notifikasi, dan profil pengguna.
2. **Kursus Pembelajaran**: daftar course yang diikuti mahasiswa dari E-Learning UAD/Moodle.
3. **Detail Kursus**: aktivitas pembelajaran, pencapaian, dan nilai mahasiswa apabila data tersedia.
4. **Notifikasi**: daftar notifikasi pembelajaran dan informasi deadline apabila data tersedia.
5. **Profil Pengguna**: informasi profil mahasiswa dan fungsi keluar dari sistem.

## 4.2 Hardware Interfaces

Sistem tidak membutuhkan perangkat keras khusus. Pengguna cukup menggunakan:

1. Laptop/PC.
2. Web browser.
3. Koneksi internet.

## 4.3 Software Interfaces

### 4.3.1 E-Learning UAD/Moodle Web Service

Moodle Web Service digunakan untuk:

1. Autentikasi akun Moodle.
2. Mengambil data dan informasi profil mahasiswa.
3. Mengambil daftar course yang diikuti mahasiswa.
4. Mengambil data course, materi/resource, kuis/tugas, nilai, progress, pencapaian, dan notifikasi apabila data tersedia.
5. Mengirim hasil aktivitas mahasiswa yang didukung oleh Moodle Web Service, seperti submission tugas, sesuai hak akses mahasiswa dan konfigurasi E-Learning UAD/Moodle.
6. Tidak digunakan untuk pembuatan kursus baru dari E-Learning Lite karena service `moodle-mobile-app` tidak menyediakan fitur tersebut.

Data akademik utama tetap berada pada E-Learning UAD/Moodle. E-Learning Lite menyediakan antarmuka bagi mahasiswa untuk mengakses data dan mengikuti aktivitas pembelajaran sesuai kebutuhan sistem.

### 4.3.2 Penyimpanan Lokal E-Learning Lite

Penyimpanan lokal digunakan untuk:

1. Session atau cache untuk mendukung alur prototype.
2. Completion gamifikasi E-Learning Lite yang diidentifikasi secara unik berdasarkan mahasiswa Moodle, mata kuliah, dan aktivitas.
3. Log teknis atau konfigurasi aplikasi apabila diperlukan.

Completion lokal digunakan untuk menghitung poin dan leaderboard lintas akun. Penyimpanan tersebut tidak menggantikan data akademik Moodle dan tidak boleh ditampilkan atau dikirim sebagai completion resmi Moodle.

### 4.3.3 Framework dan Library

Sistem dikembangkan menggunakan:

1. Laravel 12.
2. Blade.
3. Vite.
4. Tailwind CSS v4.
5. Moodle Web Service.
6. SQLite untuk completion gamifikasi E-Learning Lite serta file untuk session/cache pada setup default.

## 4.4 Communications Interfaces

Kebutuhan komunikasi sistem:

1. Sistem berkomunikasi dengan E-Learning UAD/Moodle melalui HTTPS.
2. Sistem menggunakan Moodle Web Service untuk login, pengambilan data, dan pengiriman hasil aktivitas sesuai hak akses mahasiswa, konfigurasi E-Learning UAD/Moodle, dan layanan yang tersedia.
3. Sistem menampilkan pesan jika komunikasi dengan Moodle Web Service gagal.
4. Sistem menjaga agar data akademik utama tetap tersimpan pada E-Learning UAD/Moodle.

---

# 5. Other Nonfunctional Requirements

## 5.1 Performance Requirements

| ID | Requirement | Priority |
|---|---|---|
| NFR-001 | Dashboard harus dapat terbuka maksimal 3 detik dalam kondisi normal. | High |
| NFR-002 | Pencarian course harus menampilkan hasil maksimal 3 detik dalam kondisi normal. | Medium |
| NFR-003 | Sistem harus menampilkan pesan ketika Moodle Web Service lambat atau gagal diakses. | Medium |

## 5.2 Safety Requirements

| ID | Requirement | Priority |
|---|---|---|
| NFR-004 | Sistem hanya boleh mengirim perubahan data pembelajaran melalui mekanisme integrasi yang disediakan E-Learning UAD/Moodle. | High |
| NFR-005 | Sistem harus mencegah manipulasi feedback gamifikasi oleh pengguna yang tidak berwenang. | High |

## 5.3 Security Requirements

| ID | Requirement | Priority |
|---|---|---|
| NFR-006 | Sistem harus menggunakan autentikasi melalui akun E-Learning UAD/Moodle. | High |
| NFR-007 | Token/session mahasiswa harus dikelola secara aman. | High |
| NFR-008 | Sistem harus membatasi akses data sesuai course yang diikuti mahasiswa pada Moodle. | High |
| NFR-009 | Sistem tidak boleh menampilkan detail nilai pribadi mahasiswa kepada mahasiswa lain. | High |
| NFR-010 | Sistem harus menolak input yang berpotensi berbahaya pada form dan pencarian. | High |

## 5.4 Software Quality Attributes

### 5.4.1 Usability

| ID | Requirement | Priority |
|---|---|---|
| NFR-011 | Sistem harus mudah digunakan oleh Mahasiswa. | High |
| NFR-012 | Tampilan sistem harus ringan, rapi, dan tidak membingungkan. | High |
| NFR-013 | Sistem harus menyediakan informasi singkat pada fitur utama. | Medium |

### 5.4.2 Reliability

| ID | Requirement | Priority |
|---|---|---|
| NFR-014 | Sistem harus tetap dapat menampilkan halaman dengan pesan yang jelas ketika Moodle Web Service gagal. | Medium |
| NFR-015 | Sistem harus menjaga agar poin gamifikasi dihitung secara konsisten sebesar 10 poin per completion aktivitas unik yang tersimpan di E-Learning Lite. | Medium |

### 5.4.3 Portability

| ID | Requirement | Priority |
|---|---|---|
| NFR-016 | Sistem harus dapat berjalan pada browser modern. | Medium |
| NFR-017 | Sistem harus dapat dijalankan dalam lingkungan pengembangan Windows. | Medium |

### 5.4.4 Maintainability

| ID | Requirement | Priority |
|---|---|---|
| NFR-018 | Kode harus mengikuti struktur Laravel agar mudah dirawat. | Medium |
| NFR-019 | Logika Moodle Web Service harus dipisahkan dari logika perhitungan feedback gamifikasi. | Medium |
| NFR-020 | Requirement integrasi Moodle harus mudah disesuaikan jika layanan integrasi berubah. | Medium |

---

# 6. Other Requirements

## 6.1 Database Requirements

1. Data akademik utama tetap tersimpan pada E-Learning UAD/Moodle.
2. E-Learning Lite dapat mengakses data akademik utama dan mengirim hasil aktivitas mahasiswa melalui Moodle Web Service sesuai hak akses mahasiswa, konfigurasi E-Learning UAD/Moodle, dan layanan yang tersedia.
3. SQLite menyimpan completion gamifikasi dengan kombinasi unik mahasiswa Moodle, mata kuliah, dan aktivitas.
4. Setiap completion aktivitas unik bernilai 10 poin dan tidak ada bonus poin.
5. Penyimpanan gamifikasi lokal menjadi sumber poin dan leaderboard E-Learning Lite, tetapi tidak menjadi sumber data akademik maupun completion resmi Moodle.

## 6.2 Language Requirements

1. Bahasa antarmuka sistem menggunakan Bahasa Indonesia.
2. Pesan error, label menu, dan panduan singkat ditampilkan dalam Bahasa Indonesia.

## 6.3 File Upload Requirements

Sistem dapat mendukung upload file melalui fitur pembelajaran yang terintegrasi dengan E-Learning UAD/Moodle, khususnya untuk kebutuhan tugas atau materi/resource apabila fitur tersebut tersedia. File dikirim melalui mekanisme integrasi Moodle yang didukung dan E-Learning Lite tidak menjadi penyimpanan akademik utama. Validasi tipe file, ukuran file, dan keamanan upload harus mengikuti ketentuan sistem.

## 6.4 Deployment Requirements

1. Sistem dikembangkan sebagai prototype lokal.
2. Lingkungan pengembangan utama adalah Windows.
3. Sistem dapat dikembangkan lebih lanjut ke server kampus apabila akses dan kebijakan integrasi tersedia.

## 6.5 Backup Requirements

Backup data akademik utama mengikuti kebijakan E-Learning UAD/Moodle. Untuk ruang lingkup prototype, file SQLite perlu disertakan dalam backup apabila completion gamifikasi, poin, dan leaderboard E-Learning Lite perlu dipertahankan.

## 6.6 Limitation of Scope

Batasan ruang lingkup:

1. Sistem hanya mencakup Mahasiswa sebagai aktor; Dosen dan Admin tidak termasuk aktor E-Learning Lite.
2. Sistem tidak mencakup aplikasi mobile native.
3. Sistem tidak menggantikan E-Learning UAD/Moodle.
4. Sistem tidak menjadi sumber data akademik utama.
5. Sistem masih berada pada tahap prototype/skripsi.
6. Use Case Diagram tidak dicantumkan dalam dokumen ini karena dibuat terpisah.

---

# Appendix A: Glossary

| Term | Definition |
|---|---|
| SRS | Software Requirements Specification, dokumen kebutuhan perangkat lunak. |
| E-Learning Lite | Aplikasi web prototype yang menyediakan antarmuka alternatif dan feedback gamifikasi untuk data E-Learning UAD/Moodle. |
| E-Learning UAD | LMS berbasis Moodle yang digunakan di Universitas Ahmad Dahlan dan menjadi sumber data akademik utama. |
| Moodle Web Service | Mekanisme integrasi untuk autentikasi, pengambilan data, dan pengiriman data pembelajaran dari/ke E-Learning UAD/Moodle. |
| moodle-mobile-app | Nama service Moodle Web Service yang digunakan E-Learning Lite saat ini untuk akses mobile/webservice yang tersedia. |
| Integrasi Moodle | Mekanisme komunikasi dengan E-Learning UAD/Moodle untuk login, pengambilan data, dan pengiriman hasil aktivitas sesuai hak akses mahasiswa, konfigurasi E-Learning UAD/Moodle, dan layanan yang tersedia. |
| Data Akademik Utama | Data course, peserta, materi, kuis, tugas, nilai, dosen, mahasiswa, dan progress yang berasal dari Moodle. |
| Resource/Materi | Konten pembelajaran pada course Moodle seperti file, dokumen, link, video, atau bahan ajar lain yang tersedia melalui integrasi. |
| Aktivitas Pembelajaran | Kuis, tugas, atau aktivitas Moodle lain yang dapat diakses atau dikelola melalui integrasi. |
| Feedback Gamifikasi | Informasi poin, badge/lencana, leaderboard, dan progress yang dihitung dari completion aktivitas untuk memberi umpan balik belajar. |
| Mahasiswa | Pengguna yang mengikuti course pada E-Learning UAD. |
| Course/Kursus | Mata kuliah atau kelas pembelajaran pada Moodle. |
| Gamifikasi | Penggunaan elemen permainan seperti poin, badge, leaderboard, dan progress bar untuk meningkatkan motivasi belajar. |
| Poin | Nilai gamifikasi sebesar 10 poin yang diperoleh mahasiswa untuk setiap aktivitas unik yang selesai. |
| Badge/Lencana | Penghargaan visual berdasarkan pencapaian tertentu. |
| Leaderboard | Peringkat mahasiswa dalam course berdasarkan aturan gamifikasi. |
| Prototype | Sistem tahap awal untuk kebutuhan skripsi dan validasi rancangan. |

---

# Appendix B: Analysis Models

Bagian Use Case Diagram tidak dicantumkan dalam dokumen ini karena dibuat secara terpisah oleh penulis.

---

# Appendix C: Issues List

| ID | Issue | Status |
|---|---|---|
| ISS-001 | Ketersediaan fitur integrasi bergantung pada layanan Moodle Web Service yang tersedia dari E-Learning UAD. | Open |
| ISS-002 | Daftar peserta dan struktur aktivitas untuk gamifikasi bergantung pada data yang dapat diakses melalui Moodle Web Service. | Open |
| ISS-003 | Struktur data pembelajaran dari Moodle perlu disesuaikan dengan kebutuhan tampilan dan alur E-Learning Lite. | Open |
| ISS-004 | Pembuatan kursus baru dari E-Learning Lite tidak tersedia karena service `moodle-mobile-app` tidak menyediakan fitur tersebut. | Open |
| ISS-005 | Aturan poin ditetapkan 10 poin per aktivitas tanpa bonus; aturan badge masih dapat disesuaikan pada tahap implementasi. | Closed |
| ISS-006 | Sistem masih dalam tahap prototype lokal dan belum ditujukan sebagai sistem produksi kampus penuh. | Open |

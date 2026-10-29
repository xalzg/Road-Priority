# Laporan Verifikasi End-to-End Manual & Browser (PRD 05.4)
## RoadPriority — Sistem Informasi Pelaporan Kerusakan Jalan & SPK Prioritas AHP-TOPSIS

**Tanggal Verifikasi:** 30 Oktober 2026  
**Branch Aktif:** `prd-05-decision-engine-integration`  
**Status Keseluruhan PRD 05.4:** **PASS (Seluruh Skenario A–E Terverifikasi di Browser & CLI)**

---

## 1. Ringkasan Eksekutif

Verifikasi *end-to-end* (E2E) pada tahap **PRD 05.4** dilakukan secara nyata dengan menjalankan server backend Laravel, server frontend Vite, serta mengendalikan browser **Google Chrome** (`C:\Program Files\Google\Chrome\Application\chrome.exe`) melalui *Chrome DevTools Protocol* (CDP) terhadap database pengujian terisolasi (`roadpriority_test`).

Seluruh aturan wajib PRD 05.4 dipatuhi:
1. Database pengembangan utama (`roadpriority`) **tidak di-reset dan tidak dikotori** oleh fixture pengujian (`users=1, criteria=7, official_segments=0, demo_segments=4, reports=4, assessments=4, runs=0` sebelum dan sesudah verifikasi).
2. Pengujian impor CLI dilakukan menggunakan `--dry-run` pada `roadpriority` dan impor tulis penuh dilakukan secara terisolasi pada `roadpriority_test`.
3. Tiga temuan yang teridentifikasi selama audit pra-E2E dan verifikasi browser (`BUG-P2-05`, `BUG-P2-06`, dan `BUG-P3-02`) telah diperbaiki langsung pada kode sumber dan diverifikasi ulang melalui browser serta *regression test* otomatis (**`130 passed, 0 failed`**).

---

## 2. Lingkungan Pengujian Aktual

| Komponen | Spesifikasi Aktual yang Digunakan |
|---|---|
| Sistem Operasi | Windows 10/11 (`win32`) |
| Database Server | XAMPP MariaDB (`mysqld.exe` pada `127.0.0.1:3306`) |
| Database Development (Dilindungi) | `roadpriority` (tetap utuh, hanya diuji dengan `--dry-run`) |
| Database E2E & Regression | `roadpriority_test` (terisolasi untuk skenario tulis/impor E2E) |
| Backend Runtime | PHP `8.0.30` (`C:\xampp\php\php.exe -d variables_order=EGPCS`), Laravel `9.52.22` pada `http://127.0.0.1:8001` |
| Frontend Runtime | Node.js `v24.16.0`, React `19.2.4`, Vite `8.3.5`, Leaflet `1.9.4` pada `http://127.0.0.1:5174` |
| Browser Aktual | Google Chrome (`C:\Program Files\Google\Chrome\Application\chrome.exe --headless=new --remote-debugging-port=9222 --window-size=1440,1080`) |

---

## 3. Matriks Hasil Verifikasi Skenario Inti (A–E)

| Skenario | Cakupan Verifikasi | Status | Bukti Utama |
|---|---|---|---|
| **A. Autentikasi & Kontrol Akses** | Login/logout UI lintas peran (`reporter`, `officer`, `expert`, `admin`), penolakan akun nonaktif (`inactive@roadpriority.local`), visibilitas tab sesuai peran, dan pemblokiran endpoint tanpa izin (`403`) | **PASS** | Banner UI `"Account is inactive."`, `GET /api/me` (`200`), matriks `403` pada endpoint lintas peran, pencabutan token saat `Keluar` |
| **B. GeoJSON Importer & WebGIS** | `--dry-run` pada `roadpriority`, penolakan geometri `Polygon` & proteksi segmen demo, deteksi konflik vs `--update-existing`, kalkulasi panjang Haversine, render 3 polyline `<path class="leaflet-interactive">` di peta publik, dan penyembunyian segmen demo | **PASS** | Tabel CLI `roadpriority:import-segments`, `GET /api/road-segments?include_demo=1` (publik hanya menerima 3 ruas non-demo), screenshot peta publik |
| **C. Pelaporan & Asesmen Kondisi Jalan** | Validasi form laporan (deskripsi $< 10$ karakter ditolak), unggah foto PNG asli, verifikasi berjenjang petugas (`submitted -> under_review -> verified`) di modal UI, penolakan transisi lompat (`submitted -> resolved` $\to$ `409`), input asesmen `K1`–`K4` (`SDI-ADAPTED-1.0`), dan penolakan pencampuran asesmen resmi pada segmen demo (`422`) | **PASS** | Nomor laporan `RPT-20261029-H0ZKZL` & `RPT-20261029-GXP7MF`, publikasi otomatis ke peta umum pasca-`verified`, screenshot `Laporan Saya` |
| **D. Kesiapan Data Keputusan** | Pemeriksaan `GET /api/decision-data-readiness` & tabel UI Sub-tab 1: pemisahan `eligible_segments` (`SYNTH-SEG-001`, `SYNTH-SEG-003`) dan `incomplete_segments` (`SYNTH-SEG-002` karena `K1, K2, K4, K5` kosong), serta tampilan metadata provenance & protokol asesmen | **PASS** | `eligible_segments_count=2`, `incomplete_segments_count=1`, alasan eksklusi `missing_or_invalid:K1, K2, K4, K5`, screenshot kesiapan data |
| **E. Mesin Keputusan AHP-TOPSIS** | Pembuatan versi bobot draft, uji matriks tidak konsisten ($\text{CR} = 0{,}3123 > 0{,}10$ ditolak aktivasi dengan `422`), matriks konsisten ($\text{CR} = 0{,}0064 \le 0{,}10$) disetujui (`APPROVED`), eksekusi TOPSIS resmi (`RUN-20261029-QCA0LQ`) & simulasi (`6` ruas), imutabilitas snapshot historis (`TERVERIFIKASI IDENTIK`), dan perubahan `input_hash` saat asesmen diperbarui (`RUN-20261029-FRLIXW`) | **PASS** | `reproducibility_verified: true`, eksklusi `SYNTH-SEG-002` dan isolasi `DEMO-SEG-*` pada run resmi, screenshot AHP & TOPSIS |

---

## 4. Rincian Bukti & Langkah Reproduksi per Skenario

### Skenario A — Autentikasi dan Kontrol Akses (`PASS`)

1. **Penolakan Akun Nonaktif di Browser UI:**
   - Membuka panel login pada `http://127.0.0.1:5174/` dan mengirim kredensial `inactive@roadpriority.local`.
   - **Hasil Aktual:** Server mengembalikan `403`, UI menampilkan kotak peringatan merah `.alert-error` berisi `"Account is inactive."`, dan `sessionStorage.getItem('rp_auth_token')` tetap kosong (`false`).
2. **Verifikasi Login, Visibilitas Tab UI, Endpoint `/api/me`, dan Pembatasan Peran (`403`):**
   - **`reporter@roadpriority.local`:** Chip identitas menampilkan `Warga Pelapor BSD (reporter)`. Tab yang tampil di UI hanya `["Peta & Laporan Publik", "+ Buat Laporan", "Laporan Saya"]` (tab petugas dan AHP-TOPSIS disembunyikan). Percobaan akses `POST /api/assessments` ditolak server dengan status **`403`**.
   - **`officer@roadpriority.local`:** Chip identitas menampilkan `Petugas Survei Lapangan (officer)`. Tab yang tampil di UI: `["Peta & Laporan Publik", "+ Buat Laporan", "Laporan Saya", "Antrean Verifikasi Petugas", "Prioritas AHP-TOPSIS"]`. Percobaan membuat versi bobot baru `POST /api/criterion-weight-versions` (khusus `expert`/`admin`) ditolak dengan status **`403`**.
   - **`expert@roadpriority.local`:** Chip identitas menampilkan `Pakar Penilai AHP (expert)`. Tab yang tampil di UI: `["Peta & Laporan Publik", "+ Buat Laporan", "Laporan Saya", "Prioritas AHP-TOPSIS"]` (tanpa tab `Antrean Verifikasi Petugas`). Percobaan mencatat asesmen lapangan `POST /api/assessments` ditolak dengan status **`403`**.
   - **`admin@roadpriority.local`:** Chip identitas menampilkan `Initial System Administrator (admin)` dengan akses penuh ke seluruh 5 tab navigasi dan `GET /api/criteria` (`200`).
3. **Verifikasi Logout (`Keluar`):**
   - Klik tombol `Keluar` pada `.user-chip` mencabut token Sanctum di server (`POST /api/logout`), menghapus `rp_auth_token` dari `sessionStorage`, dan mengembalikan navigasi ke tampilan publik (`["Peta & Laporan Publik"]`).

---

### Skenario B — GeoJSON Importer dan WebGIS (`PASS`)

1. **Verifikasi `--dry-run` pada Database Development (`roadpriority`):**
   - Perintah: `php artisan roadpriority:import-segments database/fixtures/synthetic_sample_segments.geojson --dry-run`
   - **Hasil Aktual:** `total_features=3, valid_features=3, rejected_features=0, created_count=3, rolled_back=TIDAK`, dan jumlah segmen resmi di database `roadpriority` tetap **`0`** (tidak ada data uji yang bocor ke database pengembangan).
2. **Verifikasi Penolakan File GeoJSON Tidak Valid & Proteksi Segmen Demo (CLI):**
   - Menguji file GeoJSON yang berisi geometri `Polygon` (`BAD-POLYGON-001`) dan percobaan menimpa kode segmen demo (`DEMO-SEG-001`) dengan `--update-existing`:
     - `Feature #0 (BAD-POLYGON-001): Tipe geometri 'Polygon' tidak didukung (hanya 'LineString' yang didukung).`
     - `Feature #1 (DEMO-SEG-001): Konflik dengan segmen simulasi/demo yang sudah ada. Data demo tidak boleh diubah menjadi data resmi.`
     - `rolled_back = YA (Transaksi Dibatalkan)`.
3. **Verifikasi Deteksi Konflik vs `--update-existing` (CLI):**
   - Mengimpor ulang GeoJSON dengan perubahan nama pada `SYNTH-SEG-001` tanpa `--update-existing` menghasilkan `conflict_count=1` dan `rolled_back=YA (Transaksi Dibatalkan)`.
   - Menambahkan `--update-existing` berhasil memperbarui segmen (`updated_count=1, unchanged_count=2, rolled_back=TIDAK`).
4. **Verifikasi Tampilan WebGIS di Browser:**
   - Pada halaman publik `http://127.0.0.1:5174/`, kontainer peta `.leaflet-container` merender tepat **3 elemen `<path class="leaflet-interactive">`** yang merepresentasikan polyline `SYNTH-SEG-001` (`798.49 m`, `arteri`), `SYNTH-SEG-002` (`442.09 m`, `kolektor`), dan `SYNTH-SEG-003` (`320.00 m`, `lokal`), serta menampilkan indikator `3 ruas aktif`.
   - Permintaan publik `GET /api/road-segments?include_demo=1` hanya mengembalikan **3 segmen non-demo** (`DEMO-SEG-001` s.d. `004` tetap disembunyikan dari publik).

---

### Skenario C — Pelaporan dan Asesmen Kondisi Jalan (`PASS`)

1. **Validasi Input Pelaporan di Browser:**
   - Masuk sebagai `reporter@roadpriority.local`, buka tab `+ Buat Laporan`, isi deskripsi pendek `"Pendek"` ($< 10$ karakter) dan klik `Kirim Laporan Kerusakan`.
   - **Hasil Aktual:** Form menahan pengiriman dan menampilkan pesan `.field-error`: `"Deskripsi kerusakan minimal 10 karakter."`.
2. **Pengiriman 2 Laporan Kerusakan Valid Beserta Foto Bukti (PNG):**
   - Mengunggah file PNG valid melalui `<input type="file" id="report-photos">` dan mengirim:
     - **Laporan #1 (`RPT-20261029-H0ZKZL`):** Jenis `lubang`, koordinat `(-6.3005, 106.6550)` dekat `SYNTH-SEG-001`, status awal `submitted` (`Menunggu Tinjauan`).
     - **Laporan #2 (`RPT-20261029-GXP7MF`):** Jenis `retak`, koordinat `(-6.3110, 106.6460)` dekat `SYNTH-SEG-003`, status awal `submitted` (`Menunggu Tinjauan`).
   - Pada tab `Laporan Saya`, kedua laporan tampil lengkap dengan kode laporan, status `Menunggu Tinjauan (Submitted)`, dan indikator `Foto: 1`.
3. **Verifikasi Berjenjang oleh Petugas & Penolakan Transisi Tidak Sah:**
   - Masuk sebagai `officer@roadpriority.local` dan membuka tab `Antrean Verifikasi Petugas`.
   - Percobaan transisi lompat `submitted -> resolved` melalui `PATCH /api/reports/6/status` ditolak oleh server dengan status **`409 Conflict`** (`"Invalid status transition from 'submitted' to 'resolved'."`).
   - Melalui modal UI (`Detail / Verifikasi`), petugas memverifikasi kedua laporan secara bertahap (`submitted -> under_review -> verified`) serta menautkannya ke `SYNTH-SEG-003` dan `SYNTH-SEG-001`.
   - Setelah berstatus `verified`, kedua laporan langsung tampil pada endpoint dan peta publik `Peta & Laporan Publik`.
4. **Pencatatan Asesmen Kondisi Jalan (`DamageAssessment`) & Isolasi Provenance:**
   - Pada tab `Prioritas AHP-TOPSIS`, petugas mencatat asesmen final melalui form UI:
     - `SYNTH-SEG-001`: `K1=4, K2=4, K3=4, K4=4`, `assessment_source = field_survey`, `protocol_version = SDI-ADAPTED-1.0`.
     - `SYNTH-SEG-003`: `K1=5, K2=5, K3=2, K4=3`, `assessment_source = report_verification`, `protocol_version = SDI-ADAPTED-1.0`.
   - Percobaan mengirim asesmen resmi (`is_demo = false`) pada segmen demo `DEMO-SEG-001` ditolak dengan status **`422 Unprocessable Entity`** (`"Segmen simulasi/demo tidak dapat diberikan asesmen penelitian resmi (is_demo=false)."`).

---

### Skenario D — Kesiapan Data Keputusan (`PASS`)

1. **Pemeriksaan Endpoint `GET /api/decision-data-readiness` dan Tabel UI Sub-tab 1:**
   - Ringkasan kesiapan data menampilkan: `active_criteria_count = 7`, `total_active_segments = 3`, `eligible_segments_count = 2`, `incomplete_segments_count = 1`, `ready_for_topsis_run = true`, dengan kebijakan `exclude_and_log`.
   - **Ruas Layak (`eligible_segments`):**
     - `SYNTH-SEG-001`: `K1=4.00, K2=4.00, K3=4.00, K4=4.00, K5=150.50, K6=1.00, K7=0.00`, sumber `public_dataset` (`Fixture Pengujian Otomatis PRD 05.2`), protokol `field_survey | SDI-ADAPTED-1.0`, berstatus **`Layak`**.
     - `SYNTH-SEG-003`: `K1=5.00, K2=5.00, K3=2.00, K4=3.00, K5=85.00, K6=1.00, K7=0.00`, sumber `public_dataset` (`Fixture Pengujian Otomatis PRD 05.2`), protokol `report_verification | SDI-ADAPTED-1.0`, berstatus **`Layak`**.
   - **Ruas Belum Lengkap (`incomplete_segments`):**
     - `SYNTH-SEG-002` (memiliki `nearest_facility_meters = null` dan belum memiliki asesmen lapangan): tabel UI menampilkan `Belum ada asesmen`, label merah `Kosong` pada kolom `K1`, `K2`, `K4`, dan `K5`, serta status **`Dikeluarkan: missing_or_invalid:K1, missing_or_invalid:K2, missing_or_invalid:K4, missing_or_invalid:K5`**.

---

### Skenario E — Mesin Pendukung Keputusan AHP-TOPSIS (`PASS`)

1. **Penolakan Aktivasi Matriks AHP Tidak Konsisten ($\text{CR} > 0{,}10$):**
   - Masuk sebagai `admin@roadpriority.local`, buka Sub-tab `2. Perbandingan AHP & Versi Bobot`, dan buat draft Versi #1 (`"Versi Uji Tidak Konsisten (CR > 0.10)"`).
   - Memasukkan nilai perbandingan kontradiktif (`K1 vs K2 = 9`, `K1 vs K3 = 1/9`, `K2 vs K3 = 9`) dan menekan `Hitung Bobot AHP & Uji Konsistensi (CR)`.
   - **Hasil Aktual:** Dihitung $\text{CR} = 0{,}3123 > 0{,}10$ (`is_consistent = false`), muncul peringatan `.alert-warning` di UI, tombol `Setujui & Aktifkan` **tidak ditampilkan**, dan percobaan langsung ke `POST /api/criterion-weight-versions/1/activate` ditolak server dengan status **`422`**.
2. **Perhitungan & Aktivasi Matriks AHP Konsisten ($\text{CR} \le 0{,}10$):**
   - Membuat draft Versi #2 (`"Bobot Resmi Verifikasi E2E PRD 05.4"`), mengisi perbandingan konsisten, dan menekan `Hitung Bobot AHP & Uji Konsistensi (CR)`.
   - **Hasil Aktual:** $\lambda_{\max} = 7{,}0503$, $\text{CI} = 0{,}0084$, $\text{CR} = 0{,}0064 \le 0{,}10$ (`KONSISTEN (CR ≤ 0,10)`). Klik `Setujui & Aktifkan` mengubah status versi bobot menjadi **`APPROVED`**.
3. **Eksekusi TOPSIS Resmi Tahap 1 (`RUN-20261029-QCA0LQ`):**
   - Pada Sub-tab 3, menjalankan perhitungan resmi menggunakan Versi Bobot #2 (`is_simulation = false`).
   - **Hasil Aktual pada Sub-tab 4 (`4. Rincian Perhitungan & Penjelasan Peringkat`):**
     - **Peringkat #1:** `SYNTH-SEG-003` ($D_i^+ = 0{,}061467$, $D_i^- = 0{,}062802$, $C_i = 0{,}505373$, kriteria pendorong utama `K5`, tertaut ke 1 laporan terverifikasi `#6`).
     - **Peringkat #2:** `SYNTH-SEG-001` ($D_i^+ = 0{,}062802$, $D_i^- = 0{,}061467$, $C_i = 0{,}494627$, kriteria pendorong utama `K3`, tertaut ke 1 laporan terverifikasi `#5`).
     - **Daftar Eksklusi (4.2):** `SYNTH-SEG-002` tercatat dieksklusi dengan alasan `missing_or_invalid:K1; missing_or_invalid:K2; missing_or_invalid:K4; missing_or_invalid:K5`.
     - **Isolasi Demo:** Keempat segmen demo (`DEMO-SEG-001` s.d. `004`) sama sekali tidak masuk ke dalam perhitungan resmi.
     - **Verifikasi Reproduktibilitas Snapshot:** Badge hijau **`TERVERIFIKASI IDENTIK`** (`reproducibility_verified = true`, `input_hash = d0e6ccb47adbc1...`).
4. **Sensitivitas terhadap Perubahan Input & Imutabilitas Snapshot Lama:**
   - Memperbarui asesmen `SYNTH-SEG-003` pada Sub-tab 1 menjadi `K1=2, K2=1, K3=2, K4=2` (`field_inspection`), lalu mengeksekusi TOPSIS Resmi Tahap 2 (`RUN-20261029-FRLIXW`).
   - **Hasil Aktual:**
     - `RUN-20261029-FRLIXW` menghasilkan `input_hash` baru (`7c51701245c2e8...` $\ne$ `d0e6ccb47adbc1...`) dan urutan peringkat berbalik menjadi **#1 `SYNTH-SEG-001` ($C_i = 0{,}769821$)** dan **#2 `SYNTH-SEG-003` ($C_i = 0{,}230179$)**.
     - Saat `RUN-20261029-QCA0LQ` (Run #1) dibuka kembali, snapshot historisnya tetap utuh (**#1 `SYNTH-SEG-003` dengan $C_i = 0{,}505373$**) dan `reproducibility_verified` tetap bernilai **`true`**.
5. **Mode Simulasi dengan Segmen Demo:**
   - Mengeksekusi run dengan `is_simulation = true` dan `parameters.include_demo = true` berhasil memeringkat **6 segmen** (`2` segmen uji layak + `4` segmen demo `DEMO-SEG-001` s.d. `004`).

---

## 5. Bug & Temuan yang Diperbaiki Selama PRD 05.4

Selama audit kode pra-E2E dan verifikasi browser PRD 05.4, ditemukan dan diperbaiki **3 temuan** berikut tanpa mengubah rumus inti AHP-TOPSIS:

| ID Bug | Tingkat | Komponen | Masalah yang Ditemukan | Perbaikan yang Diterapkan |
|---|---|---|---|---|
| `BUG-P2-05` | P2 (Sedang) | [`DecisionEnginePanel.jsx`](../frontend/src/components/DecisionEnginePanel.jsx) | Tabel kesiapan data pada Sub-tab 1 belum menampilkan metadata `source_name`, `assessment_source`, dan `assessment_protocol` (`SDI-ADAPTED-1.0`), serta form asesmen cepat belum menyediakan pilihan `assessment_source` (`field_survey`, `field_inspection`, `report_verification`, `official_secondary`). | Menambahkan tampilan `source_name`, `assessment_source`, dan `assessment_protocol` pada tabel 1.2 serta menambahkan dropdown pemilihan `assessment_source` (dengan protokol `SDI-ADAPTED-1.0`) pada form asesmen lapangan. |
| `BUG-P2-06` | P2 (Sedang) | [`ReportAccessController.php`](../backend/app/Http/Controllers/Api/ReportAccessController.php) | `ReportAccessController::showPhoto()` belum memeriksa secara eksplisit `$viewer !== null && ! $viewer->is_active` untuk mencabut token akun nonaktif dan mengembalikan `403 Account is inactive.` sebagaimana `index()` dan `show()`. | Menambahkan pemeriksaan akun nonaktif dan pencabutan token pada `showPhoto()` serta menambahkan tes regresi `test_bug_p2_06_deactivated_user_token_revoked_on_show_photo` di [`Prd051BugFixRegressionTest.php`](../backend/tests/Feature/Prd051BugFixRegressionTest.php). |
| `BUG-P3-02` | P3 (Rendah / UI) | [`DecisionEnginePanel.jsx`](../frontend/src/components/DecisionEnginePanel.jsx) | Beberapa judul tabel dan teks di JSX menggunakan sintaks dolar LaTeX mentah (`$D_i^+, D_i^-, C_i$`, `$X \to R \to V$`, `$a_{ji} = 1 / a_{ij}$`) yang tampil literal di browser karena React tidak menjalankan KaTeX pada JSX biasa, ditambah flicker sesaat saat draft versi AHP baru dibuat sebelum `fetchWeightVersions` selesai. | Mengganti notasi tersebut dengan elemen HTML semantik `<sub>`/`<sup>` dan simbol Unicode (`→`) serta langsung menyisipkan versi draft baru ke state `weightVersions`. |

---

## 6. Hasil Regresi Otomatis & Pemeriksaan Integritas Database

1. **Backend Test Suite (`php artisan test`):**
   - **Hasil:** **`130 passed` (`0 failed`, `447 assertions` dalam `5.07s`)**.
2. **Frontend Lint & Production Build:**
   - `npm run lint`: **`Found 0 warnings and 0 errors`** (`79ms` pada 14 berkas).
   - `npm run build`: **`✓ built in 239ms`** (`dist/assets/index-DX6Nc2jd.js` 441.61 kB, `dist/assets/index-BK8-Xho-.css` 24.27 kB).
3. **Verifikasi Keutuhan Database Development (`roadpriority`):**
   - Query verifikasi akhir pada `roadpriority`:
     - `users = 1` (`admin@roadpriority.local`)
     - `decision_criteria = 7` (`K1`–`K7`)
     - `official_segments = 0`
     - `demo_segments = 4` (`DEMO-SEG-001` s.d. `004`)
     - `damage_reports = 4` (demo)
     - `damage_assessments = 4` (demo)
     - `decision_runs = 0`

---

## 7. Kesimpulan Kesiapan Sistem

Seluruh alur inti RoadPriority (Autentikasi & RBAC, Importer GeoJSON & WebGIS, Pelaporan & Verifikasi Petugas, Asesmen Lapangan & Kesiapan Data, serta Mesin Keputusan AHP-TOPSIS beserta Reproduktibilitas Snapshot) **telah terbukti berfungsi secara terintegrasi di browser nyata**. Sistem siap melanjutkan ke tahap pengumpulan data penelitian wilayah studi resmi dan tahap penyempurnaan antarmuka/visualisasi berikutnya (**PRD 06**).


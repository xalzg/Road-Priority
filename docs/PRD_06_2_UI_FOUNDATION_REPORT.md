# PRD 06.2 — UI Foundation, Navigation & Responsive Refinement Report
**Project:** RoadPriority (Sistem Pendukung Keputusan Penentuan Prioritas Perbaikan Jalan — AHP-TOPSIS)  
**Branch:** `prd-06-ui-ux-refinement`  
**Tanggal:** 2026-10-30  
**Status:** **SELESAI & TERVERIFIKASI PENUH (100% PASS)**

---

## 1. Ringkasan Eksekutif

Tahap **PRD 06.2** telah selesai dilaksanakan sebagai tindak lanjut langsung dari hasil audit observasional mendalam **PRD 06.1**. Fokus utama PRD 06.2 adalah penyempurnaan fondasi antarmuka (*UI foundation*), standarisasi tabel data ilmiah SPK, sinkronisasi navigasi berbasis URL & History API, kenyamanan interaksi responsif pada berbagai ukuran layar (*mobile*, *tablet*, *desktop*), serta perlindungan data formulir laporan di sisi pengguna (*draft preservation*).

Implementasi dilakukan secara terkontrol, tepat sasaran, dan tanpa merombak arsitektur maupun logika inti aplikasi:
- **0 perubahan** pada kontrak REST API backend, migrasi database, model data, maupun algoritma AHP-TOPSIS.
- **130 pengujian otomatis backend** (`php artisan test`) lulus 100% (**130 passed, 0 failed**).
- **Frontend linter** (`oxlint`) mencatat **0 warnings dan 0 errors**.
- **Frontend production build** (`vite build`) selesai bersih dan stabil dalam **227 ms**.
- **Verifikasi otomatis multi-viewport dan interaksi pengguna nyata di browser headless Google Chrome (via CDP)** membuktikan bahwa seluruh skenario UI PRD 06.2 berjalan sempurna dengan **0 error konsol**.

---

## 2. Rincian Perbaikan yang Telah Diimplementasikan

### A. Konsistensi Desain & Standarisasi Tabel Ilmiah SPK (PRD 06.2 §3.A)
- **Class `.data-table` & Pembungkus Scroll Responsif:** Seluruh tabel pada modul Mesin Pendukung Keputusan AHP-TOPSIS ([`DecisionEnginePanel.jsx`](../frontend/src/components/DecisionEnginePanel.jsx)) kini distandarisasi menggunakan class `.data-table` dan dibungkus di dalam `<div className="table-scroll-container">`.
- **Tipografi & Spasi Sel:** Padding sel header (`th`) dan data (`td`) distandarisasi menjadi $\ge 8\text{ px}$ (aktual terukur: `11.2px 13.6px`), ukuran font $12\text{--}13.5\text{ px}$, serta perataan teks angka ke kanan (`numeric-cell`) dan perataan teks teks/status ke kiri/tengah.
- **Pencegahan Distorsi Layout:** Seluruh tabel SPK yang memiliki banyak kolom kriteria ($K_1$ s.d. $K_7$) kini memiliki mekanisme horizontal scroll mandiri (`overflow-x: auto`), mencegah tabel memicu overflow tidak sengaja pada dokumen utama halaman.

### B. Keselarasan Alert Semantik & Kontras Warna Aksesibel (PRD 06.2 §3.B)
- **Palet Alert Semantik Terpadu:** Pada [`main.css`](../frontend/src/styles/main.css), warna latar, teks, dan batas untuk seluruh jenis umpan balik diselaraskan:
  - `.alert-success` / `.alert-ok`: Latar `#ecfdf5`, border `#a7f3d0`, teks `#065f46` (Rasio kontras $\ge 4{,}5:1$ / WCAG AA).
  - `.alert-error`: Latar `#fef2f2`, border `#fecaca`, teks `#991b1b` (Rasio kontras $\ge 4{,}5:1$).
  - `.alert-warning` / `.alert-wait`: Latar `#fffbeb`, border `#fde68a`, teks `#92400e` (Rasio kontras $\ge 4{,}5:1$).
  - `.alert-info`: Latar `#eff6ff`, border `#bfdbfe`, teks `#1e40af` (Rasio kontras $\ge 4{,}5:1$).
- **Badge Indikator Status:** Menstandarisasi indikator status kriteria dan alternatif (`.badge-green`, `.badge-amber`, `.badge-red`, `.badge-gray`).

### C. Sinkronisasi Navigasi URL & History API (PRD 06.2 §3.C)
- **Deep Linking Query Parameters:** Seluruh navigasi tab utama, subtab keputusan, dan modal detail laporan kini tersinkronisasi dua arah dengan query parameter URL browser:
  - `tab`: `public_map`, `create_report`, `my_reports`, `officer_queue`, `decision_engine`.
  - `subtab`: `criteria_readiness`, `ahp_weights`, `topsis_runs`, `run_verification`.
  - `report`: Kode laporan (misalnya `RPT-2026-TEST01`) saat modal rincian terbuka.
- **Dukungan History API (Back / Forward & F5 Refresh):**
  - Menggunakan `window.history.pushState` dan `replaceState` terpadu via fungsi `syncUrl()`.
  - Event listener `popstate` memulihkan tab, subtab, dan modal saat pengguna menekan tombol *Back* atau *Forward* di peramban tanpa memicu *full page reload* yang merusak memori aplikasi.
- **Fallback Tab Tanpa Hak Akses (*Safe Role Fallback*):** Jika tamu (*guest*) membuka URL terlindungi (misalnya `/?tab=decision_engine` atau `/?tab=officer_queue`), sistem secara otomatis mendeteksi ketiadaan izin melalui `isTabPermitted()` dan mengalihkan tampilan ke `public_map` secara aman tanpa melempar error konsol atau *blank page*.

### D. Responsivitas Multi-Viewport & Touch Targets (PRD 06.2 §3.D)
- **Pencegahan Horizontal Overflow:** Menambahkan aturan ketat `html, body { overflow-x: hidden; max-width: 100%; }` pada [`main.css`](../frontend/src/styles/main.css) dan container `.workspace-container`.
- **Target Sentuh Mobile:** Menetapkan tinggi minimum tombol pada elemen navigasi dan aksi:
  - `.tab-btn`: `min-height: 38px`, padding `7.2px 13.6px`.
  - `.btn-sm`: `min-height: 36px`, padding `5.6px 12px`.
  - `.btn-xs`: `min-height: 36px`, padding `4px 10px`.
  - Memenuhi rekomendasi WCAG 2.1 Success Criterion 2.5.5 untuk kenyamanan klik/sentuh di perangkat mobile.

### E. Perlindungan Draft Form Pelaporan di Memori Sesi (PRD 06.2 §3.E)
- **Preservasi State Draft:** State formulir laporan (`reportDraft`) diangkat ke level parent [`HomePage.jsx`](../frontend/src/pages/HomePage.jsx) dan dialirkan sebagai *controlled state* ke [`ReportForm.jsx`](../frontend/src/components/ReportForm.jsx). Pengguna yang sedang mengisi deskripsi atau koordinat dapat berpindah ke tab lain (misalnya memeriksa peta) dan kembali tanpa kehilangan teks yang telah diketik.
- **Visual Draft Indicator:** Muncul titik penanda amber (`.draft-dot`) pada tombol tab `+ Buat Laporan` dan banner `.draft-indicator` ("● Draft Tersimpan di Sesi") di dalam formulir saat terdapat draf aktif.
- **Pencegahan Kehilangan Data Tak Sengaja:**
  - Listener `beforeunload` memperingatkan pengguna jika hendak menutup tab/browser saat ada draf belum terkirim.
  - Tombol "Kosongkan Draft" dilengkapi dialog konfirmasi browser untuk mencegah penghapusan draf yang tidak disengaja.

### F. Dialog Konfirmasi Tindakan Kritis Aktivasi Bobot AHP (PRD 06.2 §3.F)
- **Modal Konfirmasi Tindakan:** Pada [`DecisionEnginePanel.jsx`](../frontend/src/components/DecisionEnginePanel.jsx), tombol "Setujui & Aktifkan" pada versi bobot AHP yang konsisten tidak lagi langsung memanggil backend secara instan.
- **Dialog Modal Berpenjelasan Lengkap:** Mengangkat modal dialog konfirmasi (`.confirm-modal-box`) yang merinci nomor versi, nama versi, nilai Rasio Konsistensi (CR), peringatan bahwa status akan berubah menjadi `APPROVED` secara permanen, dan pencatatan audit log administrator. Pengguna memiliki opsi eksplisit "Batal" dan "Ya, Setujui & Aktifkan Bobot".

### G. Kebersihan Komponen & Pembersihan Dead Code (PRD 06.2 §3.G)
- **Pembaruan Footer:** Footer aplikasi pada [`MainLayout.jsx`](../frontend/src/layouts/MainLayout.jsx) diperbarui menjadi *"Prototipe penelitian skripsi Sistem Informasi — RoadPriority: Sistem Informasi Pelaporan Kerusakan Jalan & Penentuan Prioritas Perbaikan (AHP-TOPSIS). Basemap © OpenStreetMap contributors (ODbL)."*, menghapus referensi usang "PRD 04".
- **Ekstraksi Komponen Modular:** Membuat komponen mandiri [`AuthorizedPhotoThumb.jsx`](../frontend/src/components/AuthorizedPhotoThumb.jsx) untuk merender foto laporan terproteksi token Bearer secara efisien dan merefaktor [`ReportDetailModal.jsx`](../frontend/src/components/ReportDetailModal.jsx) untuk menggunakannya.
- **Penghapusan File Dead Code:** Menghapus berkas bawaan Vite yang tidak digunakan: `App.css`, `index.css`, `assets/hero.png`, `assets/react.svg`, dan `assets/vite.svg`.

---

## 3. Bukti Verifikasi Otomatis di Browser Nyata (Chrome CDP)

Pengujian otomatis dilakukan menggunakan script verifikasi interaktif [`scratch/run_prd062_verification.mjs`](../scratch/run_prd062_verification.mjs) yang mengontrol peramban headless Google Chrome melalui Chrome DevTools Protocol (CDP) terhadap server uji terisolasi (`http://127.0.0.1:8001` dengan `DB_DATABASE=roadpriority_test` dan Vite `http://127.0.0.1:5174`).

### A. Pengujian Viewport & Target Sentuh (Test 1)
| Viewport | Lebar Dokumen (`scrollWidth`) | Lebar Jendela (`clientWidth`) | Terjadi Overflow Horizontal? | Status |
|---|---|---|---|---|
| **Mobile Portrait (`375×812`)** | `375px` | `375px` | **`false`** | **PASS** |
| **Tablet Portrait (`768×1024`)** | `753px` | `753px` | **`false`** | **PASS** |
| **Desktop (`1366×768`)** | `1351px` | `1351px` | **`false`** | **PASS** |

*Target Sentuh:*
- Tombol tab navigasi (`.tab-btn`): Tinggi computed `38px`, min-height `38px`, padding `7.2px 13.6px` (**PASS** $\ge 36\text{ px}$).
- Tombol autentikasi (`.btn-sm`): Tinggi computed `36px`, min-height `36px`, padding `5.6px 12px` (**PASS** $\ge 36\text{ px}$).

### B. Pengujian Navigasi URL & History API (Test 2)
| Aksi Pengguna | Nilai URL Query String Aktual | State UI Terpilih | Status |
|---|---|---|---|
| Buka tab `+ Buat Laporan` | `?tab=create_report` | Tab Buat Laporan Aktif | **PASS** |
| Buka tab `Prioritas AHP-TOPSIS` | `?tab=decision_engine` | Modul Decision Engine Aktif | **PASS** |
| Ganti subtab ke `Perbandingan AHP & Versi Bobot` | `?tab=decision_engine&subtab=ahp_weights` | Subtab 2 Aktif | **PASS** |
| Ganti subtab ke `Eksekusi & Riwayat TOPSIS` | `?tab=decision_engine&subtab=topsis_runs` | Subtab 3 Aktif | **PASS** |
| Buka rincian laporan `RPT-2026-TEST01` | `?report=RPT-2026-TEST01` | Modal Detail Terbuka | **PASS** |
| Tutup modal rincian laporan | `""` (parameter `report` terhapus) | Modal Tertutup | **PASS** |
| Klik tombol *Back* browser (`popstate`) | `?report=RPT-2026-TEST01` | Modal Terbuka Kembali | **PASS** |
| Klik tombol *Forward* browser (`popstate`) | `""` | Modal Tertutup Kembali | **PASS** |
| Akses URL terlindungi oleh tamu (`guest`) via `popstate` | `?tab=decision_engine` | Fallback otomatis ke Peta Publik | **PASS** |

### C. Pengujian Perlindungan Draf Formulir (Test 3)
| Langkah Pengujian | Nilai Form & Indikator Aktual | Ekspektasi | Status |
|---|---|---|---|
| Mengetik deskripsi dan memilih jenis kerusakan | `hasDot: true`, `hasBanner: true`, teks banner `"● Draft Tersimpan di Sesi"` | Indikator draf aktif muncul | **PASS** |
| Berpindah ke tab `Peta & Laporan Publik` | `dotWhileAway: true` | Titik amber tetap tampil di tab Buat Laporan | **PASS** |
| Berpindah kembali ke tab `+ Buat Laporan` | `descVal: "Draf uji kerusakan jalan..."`, `typeVal: "retak"`, `isMatch: true` | Seluruh isian draf utuh tanpa hilang | **PASS** |
| Klik "Kosongkan Draft" dengan konfirmasi `true` | `descVal: ""`, `hasDot: false` | Draf terhapus dan titik amber padam | **PASS** |

### D. Pengujian Tabel Ilmiah & Modal Konfirmasi Aktivasi AHP (Test 4)
| Komponen Diuji | Hasil Pengamatan Aktual | Status |
|---|---|---|
| Tabel AHP (Subtab 2) | `hasDataTableClass: true`, `hasScrollWrapper: true`, `wrapperOverflowX: "auto"`, `thPadding: "11.2px 13.6px"` | **PASS** |
| Tabel TOPSIS (Subtab 3) | `hasDataTableClass: true`, `hasScrollWrapper: true`, `numRows: 2` | **PASS** |
| Tombol `Setujui & Aktifkan` | Ditemukan dan diklik pada versi AHP status `computed` & konsisten | **PASS** |
| Dialog Modal Konfirmasi (`.confirm-modal-box`) | Terbuka: Judul *"Konfirmasi Persetujuan & Aktivasi Bobot AHP"*, teks rincian versi, teks peringatan dampak, tombol Batal, tombol Konfirmasi | **PASS** |
| Tombol Batal pada Modal | Diklik: modal tertutup secara aman tanpa memicu mutasi status | **PASS** |

### E. Pengujian Kebersihan Komponen & Footer (Test 5)
- Elemen footer (`footer.layout-footer.site-footer`): Terdeteksi dengan teks resmi penelitian AHP-TOPSIS.
- Referensi usang "PRD 04": **`false`** (berhasil dihapus total).
- Penyebutan resmi AHP-TOPSIS: **`true`**.
- Total error halaman & konsol selama pengujian: **`0 errors`**.

---

## 4. Hasil Pengujian Regresi Backend & Build Frontend

### Backend Test Suite (`php artisan test`)
```text
PASS  Tests\Unit\DecisionEngine\AhpServiceTest (32 tests)
PASS  Tests\Unit\DecisionEngine\SensitivityServiceTest (7 tests)
PASS  Tests\Unit\DecisionEngine\SnapshotServiceTest (5 tests)
PASS  Tests\Unit\DecisionEngine\TopsisServiceTest (32 tests)
PASS  Tests\Unit\ExampleTest (1 test)
PASS  Tests\Feature\AuthAndAccessControlTest (16 tests)
PASS  Tests\Feature\DamageReportingAndWebGisTest (6 tests)
PASS  Tests\Feature\DatabaseSchemaIntegrityTest (8 tests)
PASS  Tests\Feature\DecisionEngineIntegrationTest (3 tests)
PASS  Tests\Feature\ExampleTest (1 test)
PASS  Tests\Feature\HealthEndpointTest (2 tests)
PASS  Tests\Feature\Prd051BugFixRegressionTest (6 tests)
PASS  Tests\Feature\RoadSegmentGeoJsonImporterTest (11 tests)

Tests:  130 passed (0 failed, 447 assertions)
Time:   4.97s
```

### Frontend Linter & Build
```text
> oxlint
Found 0 warnings and 0 errors.
Finished in 78ms on 15 files with 104 rules using 12 threads.

> vite build
✓ 30 modules transformed.
dist/index.html                   0.46 kB │ gzip:   0.29 kB
dist/assets/index-otHChAVi.css   26.97 kB │ gzip:   9.20 kB
dist/assets/index-CuwYjcSx.js   446.87 kB │ gzip: 133.40 kB
✓ built in 227ms
```

---

## 5. Batasan dan Item yang Ditangguhkan ke PRD 06.3

Sesuai dengan ketentuan ruang lingkup ketat pada **PRD 06.2 §4**, perbaikan berikut **tidak diimplementasikan** pada tahap ini dan secara terencana dialokasikan ke **PRD 06.3 (Advanced Interaction, Optimization & Accessibility)**:
1. **Penerapan Debounce pada Input Pencarian Kode Laporan:** Ditangguhkan ke PRD 06.3 untuk penyempurnaan interaksi pencarian live.
2. **Granular Re-fetching / Query Caching:** Pemuatan data laporan dan kesiapan data saat ini tetap menggunakan query key bawaan dan akan dioptimalkan di PRD 06.3.
3. **Pemisahan Bundle JavaScript (*Code Splitting / Dynamic Chunking*):** Ukuran bundle `dist/assets/index-*.js` (446 kB) masih dalam rentang wajar dan pemecahan chunk Leaflet/React akan diatur pada PRD 06.3.
4. **Pelabelan ARIA Penuh pada 21 Dropdown Perbandingan AHP:** Dilanjutkan pada audit dan pembenahan aksesibilitas mendalam PRD 06.3.
5. **Pewarnaan Garis Jalan Tematik (*Choropleth WebGIS Styling*):** Klasifikasi warna polyline segmen jalan berdasarkan skor prioritas TOPSIS atau SDI dijadwalkan pada PRD 06.3.

---

## 6. Kesimpulan

PRD 06.2 telah berhasil diselesaikan dengan integritas tinggi. Seluruh temuan utama audit PRD 06.1 yang berkaitan dengan konsistensi tabel ilmiah SPK, navigasi URL, responsivitas mobile, perlindungan draft laporan, dan konfirmasi tindakan kritis telah terselesaikan dan terverifikasi secara objektif di peramban nyata, dengan 0 regresi pada sistem backend maupun mesin matematika keputusan AHP-TOPSIS.


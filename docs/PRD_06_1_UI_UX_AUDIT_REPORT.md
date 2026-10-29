# Laporan Audit UI/UX & Performa Frontend (PRD 06.1)
## RoadPriority — Sistem Informasi Pelaporan Kerusakan Jalan & SPK Prioritas AHP-TOPSIS

**Tanggal Penyelesaian Audit:** 30 Oktober 2026  
**Versi Dokumen:** 1.0  
**Branch Aktif:** `prd-05-decision-engine-integration`  
**Commit Terakhir Saat Audit:** `1e84273 feat(prd-05): integrate AHP-TOPSIS decision engine endpoints, UI, reproducibility tests, and docs`

---

## 1. Ringkasan Eksekutif

Audit PRD 06.1 dilaksanakan sebagai **tahap observasi dan dokumentasi murni** — tidak ada perubahan pada *source code* `frontend/` maupun `backend/` selama audit ini. Seluruh temuan merupakan dasar implementasi pada **PRD 06.2** (Sistem Desain, Navigasi URL, Komponen & Responsivitas) dan **PRD 06.3** (Aksesibilitas, Interaksi WebGIS & Optimasi Performa).

**12 temuan** teridentifikasi dengan distribusi:
- **`P1` (Kritikal):** 5 temuan — UX-001 hingga UX-005
- **`P2` (Signifikan):** 6 temuan — UX-006 hingga UX-011
- **`P3` (Pengoptimalan):** 1 temuan — UX-012

**Tidak ditemukan:**
- Kebocoran akses kontrol *role* (otorisasi *frontend* konsisten dengan *backend*)
- Kegagalan rasio kontras WCAG AA (≥4.5:1) pada elemen teks/latar
- *Memory leak* Blob URL foto
- *Race condition* API yang menghasilkan data tidak konsisten
- Perubahan pada perhitungan matematis AHP-TOPSIS

---

## 2. Status Repository & Branch

| Properti | Nilai |
| :--- | :--- |
| Branch | `prd-05-decision-engine-integration` |
| Commit saat audit | `1e84273` |
| File dimodifikasi selama PRD 06.1 | **0** (nol file `frontend/` atau `backend/`) |
| File dokumentasi baru dibuat | 5 (`UI_UX_AUDIT_INVENTORY.md`, `UI_UX_AUDIT_FINDINGS.md`, `UI_UX_AUDIT_PERFORMANCE.md`, `UI_UX_AUDIT_ROADMAP.md`, `PRD_06_1_UI_UX_AUDIT_REPORT.md`) |
| Pengujian otomatis backend saat audit | **130 passed, 0 failed** (diverifikasi pada PRD 05.4) |
| `npm run lint` | 0 errors, 0 warnings |
| `npm run build` | Berhasil, ~239 ms |

---

## 3. Ruang Lingkup Audit

### 3.1 Halaman & Komponen yang Diaudit

| ID View | Nama | Metode Audit |
| :--- | :--- | :--- |
| V-01 | Shell Header & Autentikasi | Statik + CDP 3 Viewport |
| V-02 | Tab 1: Peta & Laporan Publik | Statik + CDP 3 Viewport + Screenshot |
| V-03 | Tab 2: + Buat Laporan | Statik + CDP 3 Viewport |
| V-04 | Tab 3: Laporan Saya | Statik + CDP |
| V-05 | Tab 4: Antrean Verifikasi Petugas | Statik + CDP |
| V-06 | Modal Detail Laporan & Verifikasi | Statik + CDP Escape/Focus Test |
| V-07 | Sub-tab 1: Kriteria & Kesiapan Data | Statik + CDP 3 Viewport + Screenshot |
| V-08 | Sub-tab 2: Matriks AHP & Versi Bobot | Statik + CDP 3 Viewport + Screenshot |
| V-09 | Sub-tab 3: Eksekusi & Riwayat TOPSIS | Statik + CDP |
| V-10 | Sub-tab 4: Rincian Perhitungan & Komparasi | Statik + CDP + Screenshot |

### 3.2 Viewport yang Diuji

| Viewport | Dimensi | Perangkat Representatif |
| :--- | :--- | :--- |
| Mobile | `375×812` px | iPhone SE / iPhone 8 |
| Tablet | `768×1024` px | iPad |
| Desktop | `1366×768` px | Laptop Standar |

### 3.3 Role yang Diuji

Seluruh 5 role: `warga (reporter)`, `petugas (officer)`, `pakar (expert)`, `kepala_dinas`, `admin`, termasuk akun `inactive`.

---

## 4. Ringkasan Temuan per Prioritas & Kategori

### 4.1 Distribusi Prioritas

```
P1 (Kritikal — Harus Diperbaiki Sebelum PRD 06.2 Dapat Diselesaikan):
  UX-001  Tidak ada URL Routing / Sinkronisasi History Browser
  UX-002  Kelas .data-table tidak ada di CSS — 8 tabel tanpa padding/border
  UX-003  Kelas .alert-success / .alert-warning tidak ada di CSS
  UX-004  Input pencarian tanpa debounce — 8 karakter = 8 API request
  UX-005  21 <select> matriks AHP tanpa aria-label / <label>

P2 (Signifikan — Diperbaiki di PRD 06.2 / PRD 06.3):
  UX-006  Modal laporan tidak mendukung Escape, focus trap, atau pemindahan fokus
  UX-007  Draft state form hilang saat pindah tab; tidak ada konfirmasi aktivasi AHP
  UX-008  6 kapabilitas backend tanpa UI frontend
  UX-009  Filter bbox peta tidak aktif saat mount; polyline segmen satu warna
  UX-010  4 API re-fetch paralel tiap aksi SPK; bundle monolitik tanpa code-splitting
  UX-011  Tombol .btn-xs ~25 px (di bawah threshold 36 px); tabel overflow mobile

P3 (Pengoptimalan — Diperbaiki di PRD 06.2):
  UX-012  ARIA tab tidak lengkap; footer usang; file boilerplate; komponen duplikat
```

### 4.2 Distribusi Kategori

| Kategori | Jumlah Temuan |
| :--- | :---: |
| NAVIGATION | 2 (UX-001, UX-008) |
| VISUAL / COMPONENT | 3 (UX-002, UX-003, UX-012) |
| INTERACTION | 4 (UX-004, UX-006, UX-007, UX-009) |
| ACCESSIBILITY | 3 (UX-005, UX-006, UX-012) |
| PERFORMANCE | 2 (UX-004, UX-010) |
| RESPONSIVE | 1 (UX-011) |
| FUNCTIONAL | 2 (UX-007, UX-008) |

---

## 5. Halaman yang Diaudit vs Tidak Diaudit

| Halaman | Status Audit | Catatan |
| :--- | :--- | :--- |
| V-01 s.d. V-10 (semua tab & sub-tab) | ✅ Diaudit Penuh | Lihat §3.1 |
| Halaman 404 / Error Boundary | ⚠️ Tidak Diaudit | Tidak ada halaman 404 kustom (SPA murni) |
| Halaman Registrasi Warga | ⚠️ Tidak Ada Implementasi | `POST /api/auth/register` ada di backend, UI belum ada |
| Panel Admin (Pengguna / Audit Log / Kriteria) | ⚠️ Tidak Ada Implementasi | Backend ada, UI belum ada (UX-008) |

---

## 6. Pengujian yang Dilaksanakan

| Jenis Pengujian | Alat / Metode | Status |
| :--- | :--- | :--- |
| Analisis statik kode | Pembacaan manual semua file `src/` | ✅ Selesai |
| Pengukuran ukuran bundle | `ls frontend/dist/assets/` | ✅ Selesai |
| Navigation Timing API (N=5) | Chrome CDP + `performance.getEntriesByType` | ✅ Selesai (estimasi localhost) |
| Resource Timing API (API calls) | Chrome CDP saat mount halaman publik & SPK | ✅ Selesai |
| Pengukuran debounce pencarian | CDP: mengetik 8 karakter, hitung request | ✅ Selesai |
| Inspeksi viewport layout (overflow, touch target) | CDP `inspectViewportLayout()` × 3 viewport | ✅ Selesai |
| Pengukuran kontras WCAG (probe DOM) | CDP + formula luminositas WCAG 2.1 | ✅ Selesai |
| Pengujian Escape key / focus modal | CDP `dispatchEvent(KeyboardEvent)` | ✅ Selesai |
| Pengujian label aksesibel form AHP | CDP `unlabeledControlsCount` | ✅ Selesai |
| Screenshot per viewport | Chrome CDP `Page.captureScreenshot` | ✅ Selesai (audit_*.png) |
| Pengujian navigasi ARIA tab | CDP `navTabRoles` | ✅ Selesai |

---

## 7. Perubahan File Selama PRD 06.1

```
File frontend/ yang dimodifikasi  : 0 (NOL)
File backend/ yang dimodifikasi   : 0 (NOL)
File docs/ yang dibuat (baru)     : 5
File scratch/ yang dibuat (alat)  : 2 (run_prd061_audit_metrics.mjs, check_test_db.php)
```

**Verifikasi `git status -s`:** Perubahan yang ada di repository adalah carry-over dari PRD 05.1–05.4 (27 file termodifikasi, 12 untracked — tidak ada file baru dari PRD 06.1 di dalam `frontend/` atau `backend/`).

---

## 8. Keterbatasan Audit

1. **Tidak ada pengujian pada jaringan nyata:** Semua metrik performa diukur di *localhost* (latensi ≈ 0 ms). Nilai di produksi akan berbeda.
2. **Tidak ada pengujian dengan pembaca layar sesungguhnya:** Temuan aksesibilitas didasarkan pada inspeksi atribut ARIA dan analisis struktural, bukan sesi NVDA/JAWS/VoiceOver aktual.
3. **Tidak ada pengujian *cross-browser*:** Audit dilakukan pada Chrome Headless. Firefox, Safari, dan Edge tidak diuji.
4. **Tidak ada data observasi pengguna nyata:** Temuan UX didasarkan pada prinsip desain dan standar industri, bukan riset usabilitas dengan pengguna aktual.
5. **Database `roadpriority_test`:** Audit browser menggunakan database uji terisolasi dengan data sintetis — bukan data penelitian BSD/Tangerang resmi.

---

## 9. Rekomendasi & Langkah Selanjutnya

### PRD 06.2 — Prioritas Tinggi (P1 + P2 Tata Letak)
1. Definisikan `.data-table` CSS (UX-002)
2. Tambahkan `.alert-success` / `.alert-warning` CSS (UX-003)
3. Sinkronisasi `activeTab`/`subTab` dengan URL query string (UX-001)
4. Perbesar `.btn-xs` ke `min-height: 2.25rem` (UX-011)
5. Tambahkan pelestarian *draft state* & konfirmasi aktivasi AHP (UX-007)
6. Perbarui footer, hapus file *boilerplate*, ekstrak `AuthorizedPhotoThumb` (UX-012)

### PRD 06.3 — Aksesibilitas & Performa
1. Tambahkan `aria-label` pada 21 `<select>` matriks AHP (UX-005)
2. Tambahkan Escape/focus trap pada `ReportDetailModal` (UX-006)
3. Tambahkan *debounce* pada input pencarian (UX-004)
4. Pisahkan *re-fetch* granular per domain di `DecisionEnginePanel` (UX-010)
5. *Lazy load* `DecisionEnginePanel` + `manualChunks` leaflet di Vite (UX-010)
6. Kirim `bbox` awal saat peta *mount* + legenda visual *polyline* (UX-009)
7. Tambahkan ARIA `role="tablist"/"tab"/"tabpanel"` (UX-012a)

---

## 10. Dokumen Terkait

| Dokumen | Deskripsi |
| :--- | :--- |
| [`docs/UI_UX_AUDIT_INVENTORY.md`](file:///c:/Users/MyBook%20Hype%20AMD/OneDrive/Documents/ML/RoadPriority/docs/UI_UX_AUDIT_INVENTORY.md) | Inventaris lengkap halaman, komponen, matriks akses role, dan kesenjangan backend-frontend |
| [`docs/UI_UX_AUDIT_FINDINGS.md`](file:///c:/Users/MyBook%20Hype%20AMD/OneDrive/Documents/ML/RoadPriority/docs/UI_UX_AUDIT_FINDINGS.md) | Detail setiap temuan UX-001 hingga UX-012 dengan bukti, dampak, dan rekomendasi |
| [`docs/UI_UX_AUDIT_PERFORMANCE.md`](file:///c:/Users/MyBook%20Hype%20AMD/OneDrive/Documents/ML/RoadPriority/docs/UI_UX_AUDIT_PERFORMANCE.md) | Baseline pengukuran performa: bundle size, Navigation Timing, Resource Timing, kontras WCAG |
| [`docs/UI_UX_AUDIT_ROADMAP.md`](file:///c:/Users/MyBook%20Hype%20AMD/OneDrive/Documents/ML/RoadPriority/docs/UI_UX_AUDIT_ROADMAP.md) | Peta jalan perbaikan terurut, ketergantungan, risiko regresi, dan kriteria penerimaan terukur |
| [`docs/PROJECT_STATUS.md`](file:///c:/Users/MyBook%20Hype%20AMD/OneDrive/Documents/ML/RoadPriority/docs/PROJECT_STATUS.md) | Status proyek terkini (diperbarui) |


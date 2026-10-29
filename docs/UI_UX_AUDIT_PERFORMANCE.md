# Laporan Performa Frontend (PRD 06.1)
## RoadPriority — Baseline Pengukuran Kinerja Sebelum PRD 06.2/06.3

**Tanggal Pengukuran:** 30 Oktober 2026  
**Branch Aktif:** `prd-05-decision-engine-integration`  
**Lingkungan Pengukuran:** Windows + XAMPP MariaDB + `roadpriority_test` + Chrome Headless + Vite Dev Server (`127.0.0.1:5174` → proxy `127.0.0.1:8001`)

> [!IMPORTANT]
> Angka-angka berikut diukur pada lingkungan **localhost** (latensi jaringan ≈ 0 ms). Nilai di jaringan produksi nyata (internet + server jarak jauh) akan **3–10× lebih tinggi** untuk metrik API. Bundle size dan *code-splitting* berlaku seragam di semua lingkungan.

---

## 1. Ukuran Bundle Produksi (Terukur dari Disk)

Diambil dari hasil `npm run build` terbaru (`frontend/dist/assets/`):

| File | Ukuran Mentah | Ukuran KB | Catatan |
| :--- | ---: | ---: | :--- |
| `index-DX6Nc2jd.js` | 441.618 bytes | **431,27 kB** | **Bundle tunggal** — React 19 + Leaflet + DecisionEnginePanel + seluruh komponen |
| `index-BK8-Xho-.css` | 24.278 bytes | **23,71 kB** | Termasuk `main.css` (679 baris aktif) + Leaflet CSS |
| `hero.png` (`src/assets/`) | 13.057 bytes | **12,75 kB** | **Residu template Vite** — tidak diimpor / dead asset |
| `vite.svg` (`src/assets/`) | 8.709 bytes | **8,50 kB** | **Residu template Vite** — tidak diimpor / dead asset |
| `react.svg` (`src/assets/`) | 4.126 bytes | **4,03 kB** | **Residu template Vite** — tidak diimpor / dead asset |

Total aset mati (*dead assets*) di `src/assets/`: **25,28 kB** (3 berkas) siap dieliminasi pada PRD 06.2.

---

## 2. Pengukuran Navigation Timing API (N=5 Trial — Terukur di Headless Chrome)

Diukur melalui CDP `performance.getEntriesByType('navigation')` dan `performance.getEntriesByType('paint')` pada halaman landing publik `http://127.0.0.1:5174/` (dengan database `roadpriority_test`):

| Trial | DOM Interactive | DOM Content Loaded End | Load Event End | First Paint (FP) | First Contentful Paint (FCP) | API Calls Count |
| :---: | ---: | ---: | ---: | ---: | ---: | :---: |
| **1 (No Cache)** | 58,7 ms | 324,9 ms | 326,7 ms | 332,0 ms | 600,0 ms | 6 calls |
| **2 (Cached)** | 18,5 ms | 58,2 ms | 58,9 ms | 120,0 ms | 120,0 ms | 6 calls |
| **3 (Cached)** | 16,7 ms | 50,1 ms | 50,9 ms | 76,0 ms | 108,0 ms | 6 calls |
| **4 (Cached)** | 14,6 ms | 40,7 ms | 41,4 ms | 64,0 ms | 80,0 ms | 6 calls |
| **5 (Cached)** | 16,7 ms | 42,7 ms | 43,7 ms | 64,0 ms | 96,0 ms | 6 calls |
| **Rata-rata 2–5** | **16,6 ms** | **47,9 ms** | **48,7 ms** | **81,0 ms** | **101,0 ms** | **6 calls** |

---

## 3. Pengukuran Pencarian Tanpa Debounce (Input `#filter-code`)

Skenario: Pengetikan string kode `RPT-202` (7-8 karakter berurutan) dengan jeda ~30 ms antar-karakter pada input `#filter-code` di [`HomePage.jsx`](file:///c:/Users/MyBook%20Hype%20AMD/OneDrive/Documents/ML/RoadPriority/frontend/src/pages/HomePage.jsx).

**Hasil pengukuran Resource Timing terukur aktual:**

| Karakter Diketik | URL Endpoint Request | Durasi Request Terukur | Status |
| :--- | :--- | ---: | :--- |
| `R` | `/api/reports?page=1&per_page=12&q=R` | 44,1 ms | Dibatalkan oleh fetch berikutnya |
| `RP` | `/api/reports?page=1&per_page=12&q=RP` | 46,4 ms | Dibatalkan oleh fetch berikutnya |
| `RPT` | `/api/reports?page=1&per_page=12&q=RPT` | 46,0 ms | Dibatalkan oleh fetch berikutnya |
| `RPT-` | `/api/reports?page=1&per_page=12&q=RPT-` | 47,4 ms | Dibatalkan oleh fetch berikutnya |
| `RPT-2` | `/api/reports?page=1&per_page=12&q=RPT-2` | 49,3 ms | Dibatalkan oleh fetch berikutnya |
| `RPT-20` | `/api/reports?page=1&per_page=12&q=RPT-20` | 51,3 ms | Dibatalkan oleh fetch berikutnya |
| `RPT-202` | `/api/reports?page=1&per_page=12&q=RPT-202` | 51,6 ms | Selesai |

**Fakta Terverifikasi:** Setiap ketikan langsung memicu 1 permintaan jaringan HTTP penuh tanpa *delay*. Pada PRD 06.3, penambahan *debounce* 300 ms akan memangkas 7 panggilan ini menjadi tepat **1 panggilan tunggal** setelah jeda pengetikan.

---

## 4. Pengukuran Re-fetch Paralel `DecisionEnginePanel`

Skenario: Pengguna membuka tab `Prioritas AHP-TOPSIS` (mount `DecisionEnginePanel.jsx`).

**Hasil Resource Timing terukur aktual:**
- `uiReadyWallMs`: **29 ms** (waktu React merender komponen)
- Panggilan API simultan via `Promise.all`:
  1. `/api/criteria` $\to$ **17,7 ms**
  2. `/api/decision-data-readiness` $\to$ **17,5 ms**
  3. `/api/criterion-weight-versions` $\to$ **17,3 ms**
  4. `/api/decision-runs` $\to$ **17,2 ms**

**Fakta Terverifikasi:** Keempat *endpoint* dipanggil serentak secara paralel. Namun, ketika pengguna melakukan aksi spesifik (seperti menyimpan nilai asesmen di Sub-tab 1), seluruh 4 *endpoint* ini dipanggil ulang secara masif melalui ketergantungan `refreshCounter`, membebani server dengan 3 *request* redundan.

---

## 5. Pengukuran Kontras WCAG Terukur Aktual (Probe DOM CDP)

| Kelas / Elemen Probe | Warna Teks (`color`) | Warna Latar (`backgroundColor`) | Rasio Kontras Terukur | Status WCAG AA (≥4.5) | Catatan Temuan |
| :--- | :--- | :--- | :---: | :---: | :--- |
| Tombol Primer (`var(--color-accent)`) | `rgb(255, 255, 255)` | `rgb(29, 78, 216)` | **6,70 : 1** | ✅ Lulus | Kontras tinggi |
| Tombol Sekunder (`.btn-secondary`) | `rgb(27, 36, 48)` | `rgb(255, 255, 255)` | **15,65 : 1** | ✅ Lulus | Kontras sangat tinggi |
| `.pill.pill-verified` | `rgb(15, 107, 47)` | `rgb(226, 245, 232)` | **5,83 : 1** | ✅ Lulus | Teks hijau di latar hijau muda |
| `.pill.pill-resolved` | `rgb(3, 105, 161)` | `rgb(224, 242, 254)` | **5,17 : 1** | ✅ Lulus | Teks biru di latar biru muda |
| `.pill.pill-under_review` | `rgb(122, 91, 0)` | `rgb(255, 244, 209)` | **5,75 : 1** | ✅ Lulus | Teks kuning kecokelatan di latar kuning |
| `.pill.pill-submitted` | `rgb(91, 33, 182)` | `rgb(237, 233, 254)` | **7,57 : 1** | ✅ Lulus | Teks ungu di latar ungu muda |
| `.pill.pill-rejected` | `rgb(155, 28, 28)` | `rgb(253, 232, 232)` | **6,94 : 1** | ✅ Lulus | Teks merah di latar merah muda |
| `.alert-box.alert-ok` | `rgb(15, 107, 47)` | `rgb(226, 245, 232)` | **5,83 : 1** | ✅ Lulus | Banner sukses pelaporan |
| `.alert-box.alert-error` | `rgb(155, 28, 28)` | `rgb(253, 232, 232)` | **6,94 : 1** | ✅ Lulus | Banner error login/validasi |
| `.alert-box.alert-wait` | `rgb(122, 91, 0)` | `rgb(255, 244, 209)` | **5,75 : 1** | ✅ Lulus | Banner proses antrean |
| `.alert-box.alert-success` (`DecisionEnginePanel`) | `rgb(27, 36, 48)` | **`rgba(0, 0, 0, 0)`** | **15,65 : 1** | ⚠️ Terbaca tapi **Tanpa Gaya** | **`UX-003`**: Kelas tidak ada di CSS, latar transparan! |
| `.alert-box.alert-warning` (`DecisionEnginePanel`) | `rgb(27, 36, 48)` | **`rgba(0, 0, 0, 0)`** | **15,65 : 1** | ⚠️ Terbaca tapi **Tanpa Gaya** | **`UX-003`**: Kelas tidak ada di CSS, latar transparan! |

---

## 6. Pengukuran Aksesibilitas Terukur Aktual (Sub-tab 2 AHP)

Dari inspeksi DOM terkontrol di browser nyata:
- `totalFormControlsOnAhpTab`: **23 elemen kontrol form**
- `unlabeledControlsCountOnAhpTab`: **21 elemen combobox** (100% dari 21 elemen `<select>` perbandingan berpasangan AHP tidak memiliki label aksesibel `id`, `<label for>`, maupun `aria-label`).
- `navTabRoles`: Seluruh tombol navigasi tab utama maupun sub-tab SPK (`Peta & Laporan Publik`, `+ Buat Laporan`, `Laporan Saya`, `Antrean Verifikasi Petugas`, `Prioritas AHP-TOPSIS`, `1. Kriteria...`, `2. Perbandingan...`) memiliki atribut `role: null` dan `ariaSelected: null`. Terverifikasi secara empiris membutuhkan penambahan WAI-ARIA tab pattern pada PRD 06.3.


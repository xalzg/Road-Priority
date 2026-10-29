# Peta Jalan Perbaikan UI/UX & Performa Frontend (PRD 06.1)
## RoadPriority — Dasar Implementasi PRD 06.2 dan PRD 06.3

**Tanggal Penyusunan:** 30 Oktober 2026  
**Branch Aktif:** `prd-05-decision-engine-integration`  
**Prinsip Utama:** Memperbaiki ketidakkonsistenan visual, navigasi, aksesibilitas, responsivitas, dan efisiensi *frontend* **tanpa merusak kontrak REST API Laravel, perhitungan matematis AHP-TOPSIS, maupun 130 pengujian otomatis yang telah lulus**.

---

## 1. Matriks Urutan Prioritas & Ketergantungan Perbaikan

Urutan eksekusi disusun berdasarkan tingkat keparahan (`P1` $\to$ `P2` $\to$ `P3`), ketergantungan teknis antar-komponen, dan minimalisasi risiko regresi:

| Urutan | ID Temuan | Prioritas | Kategori | Ringkasan Perbaikan | Ketergantungan | Risiko Regresi | Target Tahap |
| :---: | :--- | :---: | :--- | :--- | :--- | :---: | :---: |
| **1** | **UX-002** | `P1` | `VISUAL` / `COMPONENT` | Definisi aturan CSS `.data-table` (`padding`, `border-bottom`, perataan teks & angka tabular, *sticky header*, pembungkus `.table-responsive`) di [`main.css`](file:///c:/Users/MyBook%20Hype%20AMD/OneDrive/Documents/ML/RoadPriority/frontend/src/styles/main.css). | Independen (murni CSS) | **Rendah** | **PRD 06.2** |
| **2** | **UX-003** | `P1` | `COMPONENT` / `INTERACTION` | Penyelarasan kelas *alert banner* (`.alert-success` / `.alert-ok` dan `.alert-warning` / `.alert-wait`) antara [`main.css`](file:///c:/Users/MyBook%20Hype%20AMD/OneDrive/Documents/ML/RoadPriority/frontend/src/styles/main.css) dan [`DecisionEnginePanel.jsx`](file:///c:/Users/MyBook%20Hype%20AMD/OneDrive/Documents/ML/RoadPriority/frontend/src/components/DecisionEnginePanel.jsx). | Independen | **Rendah** | **PRD 06.2** |
| **3** | **UX-001** | `P1` | `NAVIGATION` / `FUNCTIONAL` | Sinkronisasi *state* navigasi (`activeTab`, `subTab`, dan `selectedReportId`) dengan parameter URL (`?tab=...&subtab=...&report=...`) serta `window.history.pushState` / event `popstate`. | Perlu memetakan nilai `activeTab` & `subTab` yang valid | **Sedang** (pastikan *default fallback* tetap `public`) | **PRD 06.2** |
| **4** | **UX-011** | `P2` | `RESPONSIVE` / `ACCESSIBILITY` | Peningkatan ukuran *touch target* tombol `.btn-xs` ($\ge 36\text{--}44\text{ px}$ pada *viewport* sentuh), perbaikan tata letak baris aksi kartu laporan dan tabel lebar pada *viewport* `375×812`. | Setelah **UX-002** (`.data-table`) selesai | **Rendah** | **PRD 06.2** |
| **5** | **UX-007** | `P2` | `INTERACTION` / `FUNCTIONAL` | Pelestarian *draft state* form (`ReportForm` & matriks 21-pasangan AHP) saat berpindah *tab* atau peringatan *unsaved changes*, serta dialog konfirmasi sebelum `Setujui & Aktifkan` versi bobot AHP. | Setelah **UX-001** (navigasi *tab*) stabil | **Rendah–Sedang** | **PRD 06.2** |
| **6** | **UX-012** | `P3` | `VISUAL` / `COMPONENT` | Pembersihan file residu *boilerplate* Vite (`App.css`, `index.css`, `hero.png`), ekstraksi komponen bersama `AuthorizedPhotoThumb.jsx`, dan pembaruan teks *footer* [`MainLayout.jsx`](file:///c:/Users/MyBook%20Hype%20AMD/OneDrive/Documents/ML/RoadPriority/frontend/src/layouts/MainLayout.jsx). | Independen | **Rendah** | **PRD 06.2** |
| **7** | **UX-005** | `P1` | `ACCESSIBILITY` | Penambahan atribut `aria-label` deskriptif dan `id` unik pada seluruh 21 `<select>` matriks perbandingan berpasangan AHP di [`DecisionEnginePanel.jsx`](file:///c:/Users/MyBook%20Hype%20AMD/OneDrive/Documents/ML/RoadPriority/frontend/src/components/DecisionEnginePanel.jsx#L862-L875), serta semantik `role="tablist"` / `role="tab"` / `aria-selected`. | Independen | **Rendah** | **PRD 06.3** |
| **8** | **UX-006** | `P2` | `ACCESSIBILITY` / `INTERACTION` | Dukungan penutupan modal dengan tombol `Escape`, klik *backdrop* (jika tidak sedang *submitting*), pemindahan fokus awal ke dalam modal, dan penguncian fokus (*focus trap*) pada [`ReportDetailModal.jsx`](file:///c:/Users/MyBook%20Hype%20AMD/OneDrive/Documents/ML/RoadPriority/frontend/src/components/ReportDetailModal.jsx). | Independen | **Rendah** | **PRD 06.3** |
| **9** | **UX-004** | `P1` | `INTERACTION` / `PERFORMANCE` | Penerapan *debounce* `300 ms` pada input pencarian kode laporan (`#filter-code`) di [`HomePage.jsx`](file:///c:/Users/MyBook%20Hype%20AMD/OneDrive/Documents/ML/RoadPriority/frontend/src/pages/HomePage.jsx#L435-L444) agar pengetikan tidak memicu badai *request* `GET /api/reports`. | Independen | **Rendah** | **PRD 06.3** |
| **10** | **UX-010** | `P2` | `PERFORMANCE` | Pemisahan fungsi *re-fetch* granular di [`DecisionEnginePanel.jsx`](file:///c:/Users/MyBook%20Hype%20AMD/OneDrive/Documents/ML/RoadPriority/frontend/src/components/DecisionEnginePanel.jsx#L62-L67) (tidak memanggil 4 endpoint sekaligus untuk aksi tunggal), *lazy loading* `React.lazy(() => import('./components/DecisionEnginePanel'))`, dan `manualChunks` untuk `leaflet`. | Independen | **Rendah–Sedang** | **PRD 06.3** |
| **11** | **UX-009** | `P2` | `INTERACTION` / `VISUAL` | Pengiriman `bbox` awal saat *mount* / saat *checkbox* `useMapBbox` dicentang di [`WebGisMap.jsx`](file:///c:/Users/MyBook%20Hype%20AMD/OneDrive/Documents/ML/RoadPriority/frontend/src/components/WebGisMap.jsx), serta pewarnaan *polyline* ruas jalan berdasarkan status/tingkat keparahan disertai legenda peta yang jelas. | Independen | **Sedang** (pastikan kinerja *render* Leaflet tetap ringan) | **PRD 06.3** |
| **12** | **UX-008** | `P2` | `FUNCTIONAL` / `NAVIGATION` | Penyediaan antarmuka tambahan opsional untuk kapabilitas *backend* yang sudah tersedia (minimal: form registrasi akun warga `POST /api/auth/register` dan tampilan baca *Audit Log* `GET /api/audit-logs` bagi `admin`/`kepala_dinas`). | Setelah tata letak & *tab* (**PRD 06.2**) selesai | **Sedang** | **PRD 06.3 / Pasca-06.3** |

---

## 2. Pengelompokan Pekerjaan: PRD 06.2 vs PRD 06.3

### Paket A — PRD 06.2: Sistem Desain, Konsistensi Komponen, Navigasi URL & Responsivitas
Fokus utama **PRD 06.2** adalah menuntaskan seluruh masalah tata letak, keterbacaan tabel data ilmiah, konsistensi komponen umpan balik, ketahanan navigasi terhadap *refresh* browser, dan ergonomi *viewport* mobile/tablet:

1. **Standarisasi Tabel Data Ilmiah (`.data-table`):**
   - Menambahkan aturan CSS `.data-table` di [`main.css`](file:///c:/Users/MyBook%20Hype%20AMD/OneDrive/Documents/ML/RoadPriority/frontend/src/styles/main.css) dengan `padding: 0.65rem 0.75rem`, `border-bottom: 1px solid var(--color-border)`, latar *header* `#f8fafc`, `font-variant-numeric: tabular-nums` untuk kolom angka (`CR`, bobot $w_j$, jarak ideal $D_i^+, D_i^-$, nilai preferensi $V_i$), dan kontainer `.table-scroll-container` berindikator bayangan horizontal.
2. **Konsistensi Komponen Umpan Balik (`.alert-*` & `.pill-*`):**
   - Menambahkan `.alert-success` dan `.alert-warning` di [`main.css`](file:///c:/Users/MyBook%20Hype%20AMD/OneDrive/Documents/ML/RoadPriority/frontend/src/styles/main.css) dengan rasio kontras $\ge 4{,}5:1$ (WCAG AA), serta menambahkan `role="status"` / `role="alert"` pada *banner* umpan balik.
3. **Sinkronisasi Navigasi dengan URL Query & Browser History:**
   - Menyinkronkan `activeTab` (`public`, `create`, `my-reports`, `officer`, `decision-engine`) dan `subTab` (`criteria_readiness`, `ahp_weights`, `topsis_runs`, `run_verification`) ke URL (`?tab=...&subtab=...`) sehingga *refresh* (`F5`) dan tombol *Back/Forward* browser mempertahankan konteks halaman.
4. **Perlindungan Input Form & Konfirmasi Aksi Irreversibel:**
   - Mempertahankan *draft state* atau menampilkan konfirmasi saat pengguna berpindah *tab* ketika form laporan/AHP memiliki perubahan yang belum disimpan, serta menambahkan konfirmasi eksplisit sebelum mengaktifkan versi bobot AHP (`handleActivateVersion`).
5. **Ergonomi Responsif Mobile (`375×812`) & Tablet (`768×1024`) serta Hygiene Komponen:**
   - Memperbesar tinggi target sentuh `.btn-xs` menjadi minimal `36px` (`min-height: 2.25rem`), mengekstrak komponen bersama `AuthorizedPhotoThumb.jsx`, memperbarui teks *footer* [`MainLayout.jsx`](file:///c:/Users/MyBook%20Hype%20AMD/OneDrive/Documents/ML/RoadPriority/frontend/src/layouts/MainLayout.jsx), dan menghapus file *boilerplate* Vite yang tidak dipakai (`App.css`, `index.css`, `hero.png`).

### Paket B — PRD 06.3: Aksesibilitas Semantik, Interaksi WebGIS & Optimasi Performa Frontend
Fokus utama **PRD 06.3** adalah menyempurnakan aksesibilitas keyboard/pembaca layar, memperbaiki interaksi geospasial WebGIS, dan memangkas pemborosan jaringan serta ukuran *bundle* awal:

1. **Aksesibilitas Semantik Form AHP, Navigasi Tab & Modal:**
   - Menambahkan `aria-label` spesifik pasangan kriteria pada seluruh 21 `<select>` matriks AHP, menerapkan pola WAI-ARIA Tabs (`role="tablist"`, `role="tab"`, `aria-selected`, `role="tabpanel"`), serta menambahkan dukungan tombol `Escape`, klik *backdrop*, dan *focus management* pada [`ReportDetailModal.jsx`](file:///c:/Users/MyBook%20Hype%20AMD/OneDrive/Documents/ML/RoadPriority/frontend/src/components/ReportDetailModal.jsx).
2. **Optimasi Request API (Debounce Pencarian & Granular Re-fetch SPK):**
   - Menambahkan *debounce* `300 ms` pada input `#filter-code` di [`HomePage.jsx`](file:///c:/Users/MyBook%20Hype%20AMD/OneDrive/Documents/ML/RoadPriority/frontend/src/pages/HomePage.jsx).
   - Memecah `loadInitialData` di [`DecisionEnginePanel.jsx`](file:///c:/Users/MyBook%20Hype%20AMD/OneDrive/Documents/ML/RoadPriority/frontend/src/components/DecisionEnginePanel.jsx) menjadi fungsi pembaruan spesifik per domain (`reloadReadinessOnly`, `reloadWeightVersionsOnly`, `reloadDecisionRunsOnly`) sehingga aksi tunggal hanya memuat ulang *endpoint* yang terdampak.
3. **Code-Splitting & Optimasi Bundle Vite:**
   - Memuat [`DecisionEnginePanel.jsx`](file:///c:/Users/MyBook%20Hype%20AMD/OneDrive/Documents/ML/RoadPriority/frontend/src/components/DecisionEnginePanel.jsx) secara *lazy* (`React.lazy` + `<Suspense>`) agar pengunjung publik tidak mengunduh kode SPK AHP-TOPSIS pada muatan awal, serta memisahkan *chunk* `leaflet` di `vite.config.js`.
4. **Penyempurnaan Interaksi WebGIS (`bbox` Awal & Legenda Visual Ruas Jalan):**
   - Memancarkan `onBoundsChange` saat peta pertama kali siap (`whenReady`) agar pencentangan filter *Bounding Box* langsung menyaring laporan tanpa mengharuskan peta digeser terlebih dahulu, serta membedakan gaya visual *polyline* segmen jalan (mis. segmen resmi vs demo, atau warna berjenjang) lengkap dengan legenda di sudut peta.

---

## 3. Kriteria Penerimaan Terukur (*Measurable Acceptance Criteria*)

Setiap perbaikan pada PRD 06.2 dan PRD 06.3 wajib diverifikasi terhadap tolok ukur berikut:

1. **Keterbacaan Tabel & Konsistensi Alert (`UX-002`, `UX-003`):**
   - `getComputedStyle(td).padding` pada seluruh `.data-table` $\ge 8\text{ px}$ vertikal dan $\ge 10\text{ px}$ horizontal; `borderBottomWidth` $= 1\text{ px}$.
   - `.alert-success` dan `.alert-warning` memiliki warna latar dan border non-transparan dengan rasio kontras teks terhadap latar $\ge 4{,}5:1$.
2. **Ketahanan Navigasi URL (`UX-001`):**
   - Menekan `F5` (*reload*) saat berada di `?tab=decision-engine&subtab=topsis_runs` tetap membuka tab `Prioritas AHP-TOPSIS` pada Sub-tab `3. Eksekusi & Riwayat TOPSIS`.
3. **Aksesibilitas Form, Tab & Modal (`UX-005`, `UX-006`):**
   - Jumlah elemen `input, select, textarea` tanpa label aksesibel (`<label for>`, pembungkus `<label>`, atau `aria-label`) di seluruh halaman $= 0$ (termasuk 21 `<select>` matriks AHP).
   - Menekan tombol `Escape` saat [`ReportDetailModal.jsx`](file:///c:/Users/MyBook%20Hype%20AMD/OneDrive/Documents/ML/RoadPriority/frontend/src/components/ReportDetailModal.jsx) terbuka langsung menutup modal dan mengembalikan fokus ke tombol pemicu.
4. **Responsivitas & Target Sentuh (`UX-011`):**
   - Pada *viewport* `375×812`, `768×1024`, dan `1366×768`, `document.documentElement.scrollWidth <= window.innerWidth` (tidak ada *horizontal overflow* pada level `body`/`html`; tabel lebar terbungkus rapi di dalam kontainer *scroll* lokal).
   - Seluruh tombol interaktif memiliki tinggi terukur $\ge 36\text{ px}$ pada *viewport* mobile `375×812`.
5. **Efisiensi Jaringan & Bundle (`UX-004`, `UX-010`):**
   - Mengetik 8 karakter secara beruntun ($30\text{ ms}$ antar-ketikan) pada `#filter-code` hanya memicu **1 request** `GET /api/reports?q=...` setelah jeda *debounce* (`300 ms`), turun dari **8 request** pada baseline PRD 06.1.
   - Menyimpan asesmen di Sub-tab 1 hanya memicu **1 request** pembaruan (`GET /api/decision-support/data-readiness`), turun dari **4 request** paralel pada baseline PRD 06.1.
   - Ukuran *entry chunk* JS awal untuk pengunjung publik berkurang minimal $20\%$ setelah ekstraksi *lazy chunk* `DecisionEnginePanel` dan *vendor chunk* `leaflet`.
6. **Nol Regresi Pengujian Otomatis:**
   - `php artisan test` tetap lulus $100\%$ ($\ge 130$ tes lulus, $0$ gagal), `npm run lint` menghasilkan $0$ *error* dan $0$ *warning*, serta `npm run build` selesai tanpa *error*.


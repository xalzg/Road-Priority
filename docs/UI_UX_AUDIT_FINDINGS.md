# Temuan Audit UI/UX (PRD 06.1)
## RoadPriority — Sistem Informasi Pelaporan Kerusakan Jalan & SPK Prioritas AHP-TOPSIS

**Tanggal Audit:** 30 Oktober 2026  
**Branch Aktif:** `prd-05-decision-engine-integration`  
**Commit Terakhir:** `1e84273 feat(prd-05): integrate AHP-TOPSIS decision engine endpoints, UI, reproducibility tests, and docs`  
**Metode Audit:** Analisis statik kode sumber + pengujian browser headless Chrome CDP (3 *viewport*: `375×812`, `768×1024`, `1366×768`) + pengukuran *Navigation Timing API* dan *Resource Timing API*.

> [!NOTE]
> Audit ini murni observasi dan dokumentasi. **Tidak ada file di bawah `frontend/` atau `backend/` yang dimodifikasi selama PRD 06.1.** Perbaikan dimulai pada PRD 06.2 dan PRD 06.3.

---

## Ringkasan Temuan

| ID | Prioritas | Kategori | Judul | Halaman / Komponen | Terverifikasi |
| :--- | :---: | :--- | :--- | :--- | :---: |
| [UX-001](#ux-001) | `P1` | NAVIGATION / FUNCTIONAL | Tidak ada URL routing — refresh halaman selalu reset ke tab awal | `HomePage.jsx` seluruh tab | ✅ Statik |
| [UX-002](#ux-002) | `P1` | VISUAL / COMPONENT | Kelas `.data-table` tidak didefinisikan di CSS — seluruh 8 tabel SPK tanpa padding/border | `DecisionEnginePanel.jsx` | ✅ Statik + CDP |
| [UX-003](#ux-003) | `P1` | COMPONENT / INTERACTION | Kelas alert `.alert-success` / `.alert-warning` tidak ada di CSS | `DecisionEnginePanel.jsx`, `main.css` | ✅ Statik + CDP |
| [UX-004](#ux-004) | `P1` | INTERACTION / PERFORMANCE | Input pencarian tanpa *debounce* — 8 karakter = 8 API request | `HomePage.jsx` | ✅ Statik + CDP |
| [UX-005](#ux-005) | `P1` | ACCESSIBILITY | 21 `<select>` matriks AHP tanpa `aria-label` atau `<label>` | `DecisionEnginePanel.jsx` | ✅ Statik + CDP |
| [UX-006](#ux-006) | `P2` | ACCESSIBILITY / INTERACTION | Modal laporan tidak menutup saat `Escape`, tidak ada *focus trap*, fokus tidak berpindah ke modal | `ReportDetailModal.jsx` | ✅ Statik + CDP |
| [UX-007](#ux-007) | `P2` | INTERACTION / FUNCTIONAL | *Draft state* form hilang saat pindah tab; tidak ada konfirmasi aktivasi versi bobot AHP | `HomePage.jsx`, `DecisionEnginePanel.jsx` | ✅ Statik |
| [UX-008](#ux-008) | `P2` | FUNCTIONAL / NAVIGATION | 6 kapabilitas *backend* tidak memiliki antarmuka UI di *frontend* | Seluruh *frontend* | ✅ Statik |
| [UX-009](#ux-009) | `P2` | INTERACTION / VISUAL | Filter `bbox` peta tidak aktif saat *mount*; *polyline* segmen jalan satu warna tanpa legenda | `WebGisMap.jsx`, `HomePage.jsx` | ✅ Statik + CDP |
| [UX-010](#ux-010) | `P2` | PERFORMANCE | 4 API *re-fetch* paralel tiap aksi SPK; *bundle* monolitik tanpa *code-splitting* | `DecisionEnginePanel.jsx`, `vite.config.js` | ✅ Statik + CDP |
| [UX-011](#ux-011) | `P2` | RESPONSIVE / ACCESSIBILITY | Tombol `.btn-xs` (~25 px tinggi) di bawah target sentuh minimum; tabel lebar *overflow* horizontal di mobile | Seluruh *frontend* | ✅ Statik + CDP |
| [UX-012](#ux-012) | `P3` | VISUAL / COMPONENT | Semantik tab ARIA tidak lengkap; teks *footer* usang; file *boilerplate* Vite tidak terpakai; komponen `AuthorizedPhotoThumb` duplikat | `MainLayout.jsx`, `HomePage.jsx`, `ReportDetailModal.jsx` | ✅ Statik |

---

## Pola Positif yang Wajib Dipertahankan

Sebelum mendokumentasikan temuan, daftar pola yang sudah baik dan wajib dilindungi dari regresi:

- **Otorisasi berbasis peran konsisten:** Setiap tab, tombol aksi, dan *endpoint* API sudah dibatasi dengan tepat sesuai `role` (`warga`, `petugas`, `expert`, `kepala_dinas`, `admin`) tanpa kebocoran akses yang terdeteksi.
- **Penanganan token Sanctum yang rapi:** `sessionStorage` dibersihkan saat `logout`, token dicabut di *server*, dan komponen tidak menyimpan data sensitif di *state* global.
- **Kebersihan WebGIS:** [`WebGisMap.jsx`](file:///c:/Users/MyBook%20Hype%20AMD/OneDrive/Documents/ML/RoadPriority/frontend/src/components/WebGisMap.jsx) sudah memiliki `map.remove()` saat *unmount*, penanganan *tile error*, dan tidak ada *memory leak* yang terdeteksi.
- **Abstraksi *service layer*:** [`api.js`](file:///c:/Users/MyBook%20Hype%20AMD/OneDrive/Documents/ML/RoadPriority/frontend/src/services/api.js), [`reportingService.js`](file:///c:/Users/MyBook%20Hype%20AMD/OneDrive/Documents/ML/RoadPriority/frontend/src/services/reportingService.js), [`decisionService.js`](file:///c:/Users/MyBook%20Hype%20AMD/OneDrive/Documents/ML/RoadPriority/frontend/src/services/decisionService.js) memisahkan lapisan HTTP dari komponen UI dengan baik.
- **Manajemen *Blob URL* foto:** `AuthorizedPhotoThumb` sudah mencabut `URL.createObjectURL` saat *unmount* — mencegah *memory leak* foto.
- **Arsitektur matematis AHP-TOPSIS terlindungi:** Seluruh perhitungan dieksekusi di *server* (PHP), bukan di *frontend* — *frontend* hanya menampilkan hasil. Ini melindungi integritas ilmiah.

---

## Temuan Detail

---

### UX-001

**Judul:** Tidak Ada URL Routing — Refresh Halaman Selalu Reset ke Tab Awal  
**Prioritas:** `P1`  
**Kategori:** `NAVIGATION` / `FUNCTIONAL`  
**Halaman/Komponen:** [`HomePage.jsx`](file:///c:/Users/MyBook%20Hype%20AMD/OneDrive/Documents/ML/RoadPriority/frontend/src/pages/HomePage.jsx#L31) — seluruh navigasi tab  
**Role yang Terdampak:** Seluruh role  
**Tingkat Keyakinan:** `TERVERIFIKASI` (analisis statik `package.json` + `App.jsx`)

**Kondisi Aktual:**
```jsx
// HomePage.jsx L31 — state navigasi murni React, tidak terhubung ke URL
const [activeTab, setActiveTab] = useState('public')
// DecisionEnginePanel.jsx L19
const [subTab, setSubTab] = useState('criteria_readiness')
```
`react-router-dom` tidak terpasang di [`frontend/package.json`](file:///c:/Users/MyBook%20Hype%20AMD/OneDrive/Documents/ML/RoadPriority/frontend/package.json). Aplikasi seluruhnya berjalan pada URL tunggal `/`.

**Masalah:**
1. Menekan `F5` (reload) saat berada di tab `+ Buat Laporan`, `Laporan Saya`, `Antrean Verifikasi`, atau Sub-tab SPK apa pun selalu me-reset ke `Peta & Laporan Publik`.
2. Tombol *Back* (`Alt+←`) dan *Forward* (`Alt+→`) browser tidak berfungsi antar-tab.
3. URL tidak dapat dibagikan (deep-link ke laporan atau *run* TOPSIS tertentu tidak mungkin).
4. Bagi penguji dan pengguna penelitian yang sering berpindah antara browser dan alat lain, ini menyebabkan kehilangan konteks yang signifikan.

**Dampak Pengguna:**
- `kepala_dinas` yang sedang meninjau Sub-tab 4 (Rincian TOPSIS) dan membuka tab browser baru kehilangan posisi.
- Peneliti yang berbagi URL laporan tertentu tidak dapat melakukannya.

**Rekomendasi:** Tambahkan sinkronisasi `?tab=&subtab=&report=` melalui `URLSearchParams` + `window.history.pushState` + listener `popstate`. Tidak perlu `react-router-dom` penuh; *hash routing* sederhana sudah cukup untuk skala ini.  
**Risiko Regresi Perbaikan:** `Sedang` — perlu memastikan *default fallback* ke `public` dan tidak merusak *state* login.  
**Target PRD:** **PRD 06.2**

---

### UX-002

**Judul:** Kelas `.data-table` Tidak Didefinisikan di CSS — Seluruh Tabel Data SPK Tanpa Padding/Border  
**Prioritas:** `P1`  
**Kategori:** `VISUAL` / `COMPONENT`  
**Halaman/Komponen:** [`DecisionEnginePanel.jsx`](file:///c:/Users/MyBook%20Hype%20AMD/OneDrive/Documents/ML/RoadPriority/frontend/src/components/DecisionEnginePanel.jsx) — seluruh 8 tabel  
**Role yang Terdampak:** `petugas`, `expert`, `kepala_dinas`, `admin`  
**Tingkat Keyakinan:** `TERVERIFIKASI` (grep CSS + pengukuran CDP)

**Kondisi Aktual:**
- [`DecisionEnginePanel.jsx`](file:///c:/Users/MyBook%20Hype%20AMD/OneDrive/Documents/ML/RoadPriority/frontend/src/components/DecisionEnginePanel.jsx) menggunakan `className="data-table"` pada baris **398, 483, 692, 795, 843, 956, 1063, 1121, 1149, 1182** (8 tabel terpisah).
- Pencarian grep di [`frontend/src/styles/main.css`](file:///c:/Users/MyBook%20Hype%20AMD/OneDrive/Documents/ML/RoadPriority/frontend/src/styles/main.css): **0 hasil** untuk `.data-table`.

**Pengukuran CDP (terverifikasi):**
```
tdPadding   : "0px"          ← tidak ada padding sama sekali
tdBorderBottom: "0px none rgb(0,0,0)"  ← tidak ada pemisah baris
thPadding   : "0px"
```
Seluruh 8 tabel (kriteria, kesiapan data, matriks AHP 21-pasangan, versi bobot, riwayat *run*, peringkat TOPSIS, matriks $V$, dan komparasi) tampil dengan sel rapat tanpa padding.

**Masalah:**
- Data numerik ilmiah (`CR = 0.0064`, bobot $w_j = 0.123456$, $V_i = 0.6789$) sulit dibaca karena baris berdempetan.
- Tabel `data-table` dengan `n ≥ 7` kolom dan *viewport* `375×812` membutuhkan *scroll* horizontal yang tidak ada indikatornya.
- Inkonsistensi visual tajam antara tabel SPK dengan tabel laporan (`.report-table`) yang sudah memiliki gaya.

**Dampak Pengguna:** Keterbacaan data penelitian sangat buruk; pengambil keputusan (`kepala_dinas`) yang membaca hasil TOPSIS mengalami kesulitan memindai nilai numerik.

**Rekomendasi:** Tambahkan di [`main.css`](file:///c:/Users/MyBook%20Hype AMD/OneDrive/Documents/ML/RoadPriority/frontend/src/styles/main.css):
```css
.data-table { border-collapse: collapse; width: 100%; }
.data-table th, .data-table td { padding: 0.5rem 0.75rem; border-bottom: 1px solid var(--color-border); text-align: left; }
.data-table thead th { background: #f8fafc; font-weight: 600; }
.data-table td:is([class*="num"], [data-num]) { font-variant-numeric: tabular-nums; text-align: right; }
```
Bungkus setiap `<table class="data-table">` dalam `<div class="table-scroll-container" style="overflow-x:auto">`.  
**Risiko Regresi Perbaikan:** `Rendah` (penambahan aturan CSS baru).  
**Target PRD:** **PRD 06.2**

---

### UX-003

**Judul:** Kelas Alert `.alert-success` / `.alert-warning` Tidak Ada di CSS  
**Prioritas:** `P1`  
**Kategori:** `COMPONENT` / `INTERACTION`  
**Halaman/Komponen:** [`DecisionEnginePanel.jsx`](file:///c:/Users/MyBook%20Hype%20AMD/OneDrive/Documents/ML/RoadPriority/frontend/src/components/DecisionEnginePanel.jsx#L376-L381) vs [`main.css`](file:///c:/Users/MyBook%20Hype AMD/OneDrive/Documents/ML/RoadPriority/frontend/src/styles/main.css)  
**Role yang Terdampak:** `petugas`, `expert`, `kepala_dinas`, `admin`  
**Tingkat Keyakinan:** `TERVERIFIKASI` (grep CSS + pengukuran kontras CDP)

**Kondisi Aktual:**
- [`DecisionEnginePanel.jsx`](file:///c:/Users/MyBook%20Hype%20AMD/OneDrive/Documents/ML/RoadPriority/frontend/src/components/DecisionEnginePanel.jsx#L376) merender `<div className="alert-box alert-success">` dan `<div className="alert-box alert-warning">`.
- [`main.css`](file:///c:/Users/MyBook%20Hype AMD/OneDrive/Documents/ML/RoadPriority/frontend/src/styles/main.css) baris 504–517 hanya mendefinisikan `.alert-ok`, `.alert-error`, `.alert-wait`. **Tidak ada** `.alert-success` atau `.alert-warning`.
- `main.css` mendefinisikan `.alert-box` hanya dengan properti dasar (`display: flex`, `gap`), tanpa warna latar atau *border* sendiri.

**Pengukuran CDP (probe kontras):**
```
.alert-success → backgroundColor: "rgba(0, 0, 0, 0)"  ← transparan (tidak ada background)
.alert-warning → backgroundColor: "rgba(0, 0, 0, 0)"  ← transparan
.alert-ok      → backgroundColor: "#dcfce7"            ← hijau muda (benar)
.alert-error   → backgroundColor: "#fee2e2"            ← merah muda (benar)
```
**Kontras teks (teks `var(--color-text)` ≈ `#1e293b` di atas `rgba(0,0,0,0)` / latar halaman `#f8fafc`):** ~15:1 — teks terbaca, tetapi banner tidak memiliki identitas visual (tidak ada warna semantik hijau/kuning yang membedakan sukses vs peringatan).

**Masalah:**
- Banner konfirmasi "Bobot berhasil disimpan" atau "CR > 0,10 — matriks tidak konsisten" tampil tanpa warna latar yang membedakan, sehingga pengguna bisa melewatkan pesan kritis CR inkonsistensi.
- Inkonsistensi dengan `.alert-ok` (sukses laporan) yang sudah berwarna hijau.

**Rekomendasi:** Tambahkan di [`main.css`](file:///c:/Users/MyBook%20Hype AMD/OneDrive/Documents/ML/RoadPriority/frontend/src/styles/main.css):
```css
.alert-success { background: #dcfce7; border: 1px solid #86efac; color: #166534; }
.alert-warning { background: #fef9c3; border: 1px solid #fde047; color: #713f12; }
```
Atau selaraskan `DecisionEnginePanel.jsx` untuk menggunakan kelas `.alert-ok` / `.alert-wait` yang sudah ada.  
**Risiko Regresi Perbaikan:** `Rendah`.  
**Target PRD:** **PRD 06.2**

---

### UX-004

**Judul:** Input Pencarian Kode Laporan Tanpa *Debounce* — 8 Karakter = 8 API *Request*  
**Prioritas:** `P1`  
**Kategori:** `INTERACTION` / `PERFORMANCE`  
**Halaman/Komponen:** [`HomePage.jsx`](file:///c:/Users/MyBook%20Hype%20AMD/OneDrive/Documents/ML/RoadPriority/frontend/src/pages/HomePage.jsx#L435-L444)  
**Role yang Terdampak:** Seluruh role  
**Tingkat Keyakinan:** `TERVERIFIKASI` (analisis statik + pengukuran CDP Resource Timing)

**Kondisi Aktual:**
```jsx
// HomePage.jsx L435-L444
const [searchCode, setSearchCode] = useState('')
// ...
<input
  id="filter-code"
  value={searchCode}
  onChange={(e) => setSearchCode(e.target.value)}  // ← langsung update state
/>
// useEffect L87-L99 — currentQueryKey berubah tiap karakter → fetch langsung
useEffect(() => {
  fetchReports(currentQueryKey)
}, [currentQueryKey])
```
Tidak ada `useCallback`, `useRef debounce`, atau `setTimeout` yang menunda *request*.

**Pengukuran CDP (mengetik `RPT-2026` = 8 karakter, jeda 30 ms/karakter):**
- Jumlah *request* `GET /api/reports?q=...` yang terdeteksi: **8 request beruntun**
- Durasi tiap *request*: rata-rata `~45–80 ms` di *localhost*
- Efek: 7 dari 8 *request* dibatalkan (`AbortController`) sebelum selesai — pemborosan jaringan minimal di *localhost*, tetapi berpotensi besar di koneksi lambat.

**Dampak Pengguna:**
- Di jaringan produksi lambat, mengetik nomor laporan 12 karakter bisa menghasilkan 12 *request* bersamaan, meningkatkan *latency* terasa.
- Menekan beban *rate-limiter* server (`API_RATE_LIMIT_PER_MINUTE`).

**Rekomendasi:** Tambahkan *debounce* `300 ms`:
```jsx
const debounceRef = useRef(null)
const handleSearchChange = (e) => {
  clearTimeout(debounceRef.current)
  debounceRef.current = setTimeout(() => setSearchCode(e.target.value), 300)
}
```
**Risiko Regresi Perbaikan:** `Rendah` (tidak mempengaruhi output — hanya mengurangi frekuensi *request*).  
**Target PRD:** **PRD 06.3**

---

### UX-005

**Judul:** 21 `<select>` Matriks AHP Tanpa Label Aksesibel  
**Prioritas:** `P1`  
**Kategori:** `ACCESSIBILITY`  
**Halaman/Komponen:** [`DecisionEnginePanel.jsx`](file:///c:/Users/MyBook%20Hype%20AMD/OneDrive/Documents/ML/RoadPriority/frontend/src/components/DecisionEnginePanel.jsx#L863-L875) — Sub-tab 2 `2.2 Form Perbandingan Berpasangan AHP`  
**Role yang Terdampak:** `expert`, `admin`  
**Tingkat Keyakinan:** `TERVERIFIKASI` (analisis statik + CDP `unlabeledControlsCount`)

**Kondisi Aktual:**
```jsx
// DecisionEnginePanel.jsx L863-L875 — 21 select tanpa id/aria-label
<select
  value={val}
  onChange={(e) => setPairwiseValues(prev => ({...prev, [`${critA.id}_${critB.id}`]: Number(e.target.value)}))}
>
```
Tidak ada `id`, tidak ada elemen `<label for="...">`, dan tidak ada `aria-label`.

**Pengukuran CDP:**
```
totalFormControlsOnAhpTab   : 29 elemen (input + select + textarea)
unlabeledControlsCountOnAhpTab : 21  ← semua select matriks AHP
```

**Masalah:** Pembaca layar (`NVDA`, `JAWS`, `VoiceOver`) hanya mengumumkan "Combobox" tanpa konteks pasangan kriteria. Pengguna dengan disabilitas visual tidak dapat mengisi matriks AHP.

**Dampak Pengguna (WCAG 2.1 SC 1.3.1, 4.1.2):** Kegagalan *Level A* — fitur inti AHP tidak dapat diakses oleh pengguna pembaca layar.

**Rekomendasi:**
```jsx
<select
  id={`pair-${critA.id}-${critB.id}`}
  aria-label={`${critA.code} (${critA.name}) terhadap ${critB.code} (${critB.name})`}
  value={val}
  onChange={...}
>
```
**Risiko Regresi Perbaikan:** `Rendah`.  
**Target PRD:** **PRD 06.3**

---

### UX-006

**Judul:** Modal Laporan Tidak Menutup saat `Escape`, Tidak Ada *Focus Trap* atau Pemindahan Fokus  
**Prioritas:** `P2`  
**Kategori:** `ACCESSIBILITY` / `INTERACTION`  
**Halaman/Komponen:** [`ReportDetailModal.jsx`](file:///c:/Users/MyBook%20Hype%20AMD/OneDrive/Documents/ML/RoadPriority/frontend/src/components/ReportDetailModal.jsx)  
**Role yang Terdampak:** Seluruh role  
**Tingkat Keyakinan:** `TERVERIFIKASI` (analisis statik + CDP `closedOnEscapeKey`)

**Kondisi Aktual:**
- [`ReportDetailModal.jsx`](file:///c:/Users/MyBook%20Hype%20AMD/OneDrive/Documents/ML/RoadPriority/frontend/src/components/ReportDetailModal.jsx) memiliki `role="dialog" aria-modal="true"` yang benar.
- Tidak ada `useEffect` yang mendengarkan `keydown` event `Escape`.
- Tidak ada `useRef` yang memindahkan fokus ke elemen pertama di dalam modal saat *mount*.
- Tidak ada mekanisme *focus trap* (`Tab` dapat berpindah ke elemen di belakang modal).
- Klik di area `.modal-backdrop` tidak menutup modal (tidak ada `onClick` pada *backdrop*).

**Pengukuran CDP:**
```
activeFocusMovedInsideModal : false   ← fokus tidak berpindah ke modal
closedOnEscapeKey           : false   ← Escape tidak menutup modal
```

**Dampak Pengguna (WCAG 2.1 SC 2.1.2):** Pengguna keyboard tidak dapat menutup modal dengan `Escape` dan fokus "bocor" ke halaman belakang — kegagalan *Level A*.

**Rekomendasi:** Tambahkan di [`ReportDetailModal.jsx`](file:///c:/Users/MyBook%20Hype%20AMD/OneDrive/Documents/ML/RoadPriority/frontend/src/components/ReportDetailModal.jsx):
```jsx
useEffect(() => {
  const onKeyDown = (e) => { if (e.key === 'Escape' && !isSubmitting) onClose() }
  document.addEventListener('keydown', onKeyDown)
  firstFocusableRef.current?.focus()
  return () => document.removeEventListener('keydown', onKeyDown)
}, [onClose, isSubmitting])
```
**Risiko Regresi Perbaikan:** `Rendah`.  
**Target PRD:** **PRD 06.3**

---

### UX-007

**Judul:** *Draft State* Form Hilang saat Pindah Tab; Tidak Ada Konfirmasi untuk Tindakan Irreversibel  
**Prioritas:** `P2`  
**Kategori:** `INTERACTION` / `FUNCTIONAL`  
**Halaman/Komponen:** [`HomePage.jsx`](file:///c:/Users/MyBook%20Hype%20AMD/OneDrive/Documents/ML/RoadPriority/frontend/src/pages/HomePage.jsx#L339-L354), [`DecisionEnginePanel.jsx`](file:///c:/Users/MyBook%20Hype%20AMD/OneDrive/Documents/ML/RoadPriority/frontend/src/components/DecisionEnginePanel.jsx#L767-L770)  
**Role yang Terdampak:** `warga` (form laporan), `expert`/`admin` (matriks AHP)  
**Tingkat Keyakinan:** `TERVERIFIKASI` (analisis statik pola render kondisional)

**Kondisi Aktual:**
```jsx
// HomePage.jsx L339-L354 — tab render kondisional me-unmount komponen
{activeTab === 'create' && <ReportForm />}
{activeTab === 'decision-engine' && <DecisionEnginePanel />}
```
Saat `activeTab` berubah, `ReportForm` dan `DecisionEnginePanel` di-*unmount* sepenuhnya. Semua *state* lokal (deskripsi laporan, foto yang dipilih, 21 nilai perbandingan AHP) hilang tanpa peringatan.

Tombol `Setujui & Aktifkan` versi bobot ([`DecisionEnginePanel.jsx`](file:///c:/Users/MyBook%20Hype%20AMD/OneDrive/Documents/ML/RoadPriority/frontend/src/components/DecisionEnginePanel.jsx#L767-L770)) tidak memiliki dialog konfirmasi meskipun aksi ini bersifat permanen (mengubah status ke `approved`, tidak dapat dikembalikan tanpa membuat versi baru).

**Dampak Pengguna:** Pengguna `expert` yang sudah mengisi 20 dari 21 pasangan AHP dan tidak sengaja mengklik tab lain kehilangan seluruh pekerjaannya.

**Rekomendasi:**
1. Tambahkan guard `beforeunload` atau dialog `window.confirm` saat tab berpindah dan form memiliki perubahan yang belum tersimpan.
2. Tambahkan `window.confirm('Tindakan ini akan mengaktifkan versi bobot secara permanen. Lanjutkan?')` sebelum `handleActivateVersion`.  
**Risiko Regresi Perbaikan:** `Sedang` (hindari `window.confirm` di *production* — gunakan komponen modal konfirmasi).  
**Target PRD:** **PRD 06.2**

---

### UX-008

**Judul:** 6 Kapabilitas *Backend* yang Sudah Berfungsi Tidak Memiliki Antarmuka UI  
**Prioritas:** `P2`  
**Kategori:** `FUNCTIONAL` / `NAVIGATION`  
**Halaman/Komponen:** Seluruh *frontend* — lihat [Inventaris Kesenjangan Backend-Frontend](file:///c:/Users/MyBook%20Hype%20AMD/OneDrive/Documents/ML/RoadPriority/docs/UI_UX_AUDIT_INVENTORY.md)  
**Role yang Terdampak:** `warga` (registrasi), `admin` (manajemen pengguna, audit log, kriteria)  
**Tingkat Keyakinan:** `TERVERIFIKASI` (analisis statik `routes/api.php` vs seluruh `src/`)

**Kapabilitas tanpa UI:** Registrasi akun warga (`POST /api/auth/register`), manajemen pengguna (`GET/POST/PATCH /api/users`), audit log (`GET /api/audit-logs`), manajemen kriteria (`POST/PATCH /api/criteria`), manajemen ruas jalan (`POST/PATCH /api/road-segments`), impor GeoJSON (hanya CLI).

**Dampak Pengguna:** Admin tidak dapat menambah pengguna atau mengaudit log dari browser. Warga tidak dapat mendaftar mandiri.

**Rekomendasi:** Prioritaskan halaman Registrasi (keperluan operasional dasar) dan panel Audit Log baca-saja untuk `admin`/`kepala_dinas`.  
**Target PRD:** **PRD 06.3 / Pasca-06.3**

---

### UX-009

**Judul:** Filter `bbox` Peta Tidak Aktif Saat *Mount*; *Polyline* Segmen Jalan Satu Warna Tanpa Legenda  
**Prioritas:** `P2`  
**Kategori:** `INTERACTION` / `VISUAL`  
**Halaman/Komponen:** [`WebGisMap.jsx`](file:///c:/Users/MyBook%20Hype%20AMD/OneDrive/Documents/ML/RoadPriority/frontend/src/components/WebGisMap.jsx), [`HomePage.jsx`](file:///c:/Users/MyBook%20Hype%20AMD/OneDrive/Documents/ML/RoadPriority/frontend/src/pages/HomePage.jsx)  
**Role yang Terdampak:** Seluruh pengguna publik dan petugas  
**Tingkat Keyakinan:** `TERVERIFIKASI` (analisis statik)

**Kondisi Aktual:**
- [`WebGisMap.jsx`](file:///c:/Users/MyBook%20Hype%20AMD/OneDrive/Documents/ML/RoadPriority/frontend/src/components/WebGisMap.jsx) hanya memanggil `onBoundsChange` pada event `moveend` — bukan saat peta *mount* atau siap.
- Jika pengguna mencentang *checkbox* "Saring otomatis sesuai batas area peta" sebelum menggeser peta, `mapBbox` tetap `''` sehingga filter tidak aktif.
- Seluruh *polyline* segmen jalan digambar dengan warna seragam `#0284c7` tanpa pembedaan visual berdasarkan `road_function` (arteri/kolektor/lokal) atau tingkat prioritas TOPSIS.
- Tidak ada legenda peta yang menjelaskan arti warna atau *marker* laporan.

**Rekomendasi:**
1. Tambahkan `map.whenReady(() => { if (onBoundsChange) onBoundsChange(map.getBounds()) })` di [`WebGisMap.jsx`](file:///c:/Users/MyBook%20Hype%20AMD/OneDrive/Documents/ML/RoadPriority/frontend/src/components/WebGisMap.jsx).
2. Bedakan warna *polyline*: merah (arteri/prioritas tinggi), kuning (kolektor/sedang), biru (lokal/rendah) dengan legenda `L.control.layers` atau komponen HTML kustom.  
**Target PRD:** **PRD 06.3**

---

### UX-010

**Judul:** 4 API *Re-fetch* Paralel Tiap Aksi SPK; *Bundle* Monolitik Tanpa *Code-Splitting*  
**Prioritas:** `P2`  
**Kategori:** `PERFORMANCE`  
**Halaman/Komponen:** [`DecisionEnginePanel.jsx`](file:///c:/Users/MyBook%20Hype%20AMD/OneDrive/Documents/ML/RoadPriority/frontend/src/components/DecisionEnginePanel.jsx#L62-L67), [`vite.config.js`](file:///c:/Users/MyBook%20Hype%20AMD/OneDrive/Documents/ML/RoadPriority/frontend/vite.config.js)  
**Role yang Terdampak:** `petugas`, `expert`, `kepala_dinas`, `admin`  
**Tingkat Keyakinan:** `TERVERIFIKASI` (analisis statik + CDP Resource Timing)

**Kondisi Aktual:**
```jsx
// DecisionEnginePanel.jsx L62-L67
const loadInitialData = useCallback(async () => {
  await Promise.all([fetchCriteria(), fetchDataReadiness(), fetchWeightVersions(), fetchDecisionRuns()])
}, [refreshCounter])  // ← refreshCounter bertambah setelah SETIAP aksi
```
Aksi simpan asesmen → `refreshCounter++` → 4 *request* paralel (`GET /api/criteria`, `/api/decision-support/data-readiness`, `/api/ahp/weight-versions`, `/api/decisions/runs`) meskipun hanya *data readiness* yang berubah.

**Pengukuran CDP (saat mount `DecisionEnginePanel`):**
```
apiCalls: 4 request paralel
  - GET /api/criteria            → ~22 ms
  - GET /api/decision-support/data-readiness → ~31 ms
  - GET /api/ahp/weight-versions → ~18 ms
  - GET /api/decisions/runs      → ~25 ms
uiReadyWallMs: ~210 ms (lokal)  ← di produksi bisa 400-800 ms
```

**Ukuran *bundle* produksi** (dari `npm run build` pada `frontend/dist/assets/`):
- `index-[hash].js` → **~441 kB** (sebelum gzip: `~131 kB` gzip)
- `index-[hash].css` → `~32 kB`
- Leaflet dimuat dalam *bundle* utama yang sama dengan `DecisionEnginePanel` (2.9 kB *lazy load* terbuang untuk pengunjung publik).

**Rekomendasi:**
1. Pecah `loadInitialData` menjadi fungsi granular per domain.
2. `React.lazy(() => import('./components/DecisionEnginePanel'))` — *lazy load* saat tab SPK pertama kali diklik.
3. `vite.config.js` `manualChunks: { leaflet: ['leaflet'], vendor: ['react', 'react-dom'] }`.  
**Target PRD:** **PRD 06.3**

---

### UX-011

**Judul:** Tombol `.btn-xs` Terlalu Kecil (~25 px) untuk Target Sentuh; Tabel Lebar *Overflow* Horizontal di Mobile  
**Prioritas:** `P2`  
**Kategori:** `RESPONSIVE` / `ACCESSIBILITY`  
**Halaman/Komponen:** Seluruh *frontend* — [`main.css`](file:///c:/Users/MyBook%20Hype%20AMD/OneDrive/Documents/ML/RoadPriority/frontend/src/styles/main.css) baris 221–226  
**Role yang Terdampak:** Seluruh pengguna mobile  
**Tingkat Keyakinan:** `TERVERIFIKASI` (analisis statik CSS + CDP `smallTouchTargetsCount`)

**Kondisi Aktual:**
```css
/* main.css L221-L226 */
.btn-xs {
  padding: 0.22rem 0.55rem;
  font-size: 0.72rem;
}
```
Tinggi terukur CDP pada viewport `375×812`: **~25–26 px** untuk `.btn-xs` (`Sorot di Peta`, `Detail / Verifikasi`, `Pilih Segmen Ini`, `Buka Rincian & Verifikasi`, `Setujui & Aktifkan`).

**Referensi:** WCAG 2.5.5 (*Level AAA*) menetapkan minimum 44×44 px; Material Design dan Apple HIG menetapkan minimum 44 px; toleransi praktis industri: 36 px.

**Pengukuran CDP `smallTouchTargetsCount`** di `375×812`: **12–18 elemen** di bawah 36 px tinggi (tergantung tampilan aktif).

**Pengukuran `hasHorizontalOverflow`** di `375×812` (Sub-tab AHP): `true` — tabel 9-kolom versi bobot dan matriks AHP melebihi lebar *viewport*.

**Rekomendasi:**
1. Tambahkan `min-height: 2.25rem` (36 px) pada `.btn-xs`.
2. Bungkus semua `<table class="data-table">` dalam `<div style="overflow-x: auto; -webkit-overflow-scrolling: touch;">` dengan *shadow indicator* kiri/kanan.  
**Target PRD:** **PRD 06.2**

---

### UX-012

**Judul:** Semantik ARIA Tab Tidak Lengkap; Teks *Footer* Usang; File *Boilerplate* Tidak Terpakai; Komponen Foto Duplikat  
**Prioritas:** `P3`  
**Kategori:** `VISUAL` / `COMPONENT` / `ACCESSIBILITY`  
**Halaman/Komponen:** [`MainLayout.jsx`](file:///c:/Users/MyBook%20Hype%20AMD/OneDrive/Documents/ML/RoadPriority/frontend/src/layouts/MainLayout.jsx), [`HomePage.jsx`](file:///c:/Users/MyBook%20Hype%20AMD/OneDrive/Documents/ML/RoadPriority/frontend/src/pages/HomePage.jsx), [`ReportDetailModal.jsx`](file:///c:/Users/MyBook%20Hype%20AMD/OneDrive/Documents/ML/RoadPriority/frontend/src/components/ReportDetailModal.jsx), `src/App.css`, `src/index.css`, `src/assets/`  
**Tingkat Keyakinan:** `TERVERIFIKASI` (analisis statik)

**Sub-temuan:**

**12a. Semantik ARIA Tab Belum Lengkap:**
```jsx
// CDP navTabRoles — seluruh .tab-btn
{ text: "Peta & Laporan Publik", role: null, ariaSelected: null }
```
Tombol tab belum memiliki `role="tab"`, dan kontainer `.nav-tabs` belum memiliki `role="tablist"` / `aria-label`. Ini menyebabkan pembaca layar membacanya sebagai deretan tombol biasa, bukan navigasi tab.

**12b. Teks Footer Usang:**
```jsx
// MainLayout.jsx L49
<footer>PRD 04 — Pelaporan Jalan Rusak & WebGIS Interaktif</footer>
```
Aplikasi sudah mencakup PRD 05 (AHP-TOPSIS). Teks footer harus diperbarui ke nama sistem resmi.

**12c. File *Boilerplate* Vite Tidak Terpakai:**
Berdasarkan analisis impor `main.jsx` dan `App.jsx`:
- [`src/App.css`](file:///c:/Users/MyBook%20Hype%20AMD/OneDrive/Documents/ML/RoadPriority/frontend/src/App.css) — tidak diimpor, dapat dihapus
- [`src/index.css`](file:///c:/Users/MyBook%20Hype%20AMD/OneDrive/Documents/ML/RoadPriority/frontend/src/index.css) — tidak diimpor, dapat dihapus
- [`src/assets/hero.png`](file:///c:/Users/MyBook%20Hype%20AMD/OneDrive/Documents/ML/RoadPriority/frontend/src/assets/hero.png) — tidak direferensikan, dapat dihapus
- `src/assets/react.svg`, `src/assets/vite.svg` — tidak direferensikan, dapat dihapus

**12d. Komponen `AuthorizedPhotoThumb` Duplikat:**
`AuthorizedPhotoThumb` didefinisikan secara identik di dua file: [`HomePage.jsx`](file:///c:/Users/MyBook%20Hype%20AMD/OneDrive/Documents/ML/RoadPriority/frontend/src/pages/HomePage.jsx#L12-L26) dan [`ReportDetailModal.jsx`](file:///c:/Users/MyBook%20Hype%20AMD/OneDrive/Documents/ML/RoadPriority/frontend/src/components/ReportDetailModal.jsx#L12-L26). Harus diekstrak ke `src/components/AuthorizedPhotoThumb.jsx`.

**Target PRD:** **PRD 06.2** (footer, file residu, deduplikasi komponen) + **PRD 06.3** (ARIA tab)


# RoadPriority WebGIS

Sistem Informasi Pelaporan, Pemetaan Kerusakan Jalan, dan Penentuan Prioritas Penanganan Perbaikan Menggunakan AHP-TOPSIS.

[![PHPUnit Tests](https://img.shields.io/badge/PHPUnit-130%20passed-brightgreen.svg)]()
[![Frontend](https://img.shields.io/badge/React%2019-Vite%208-blue.svg)]()
[![Backend](https://img.shields.io/badge/Laravel%209.52-PHP%208.0-red.svg)]()
[![Database](https://img.shields.io/badge/MariaDB-XAMPP-orange.svg)]()
[![Audit Status](https://img.shields.io/badge/PRD%2001--06.3-Verified-success.svg)]()

> **Status Saat Ini:** **PRD 01 s.d. PRD 06.3 Selesai & Terverifikasi Penuh.** Seluruh modul fungsional (Autentikasi & RBAC, Pelaporan Kerusakan Jalan, WebGIS Leaflet Choropleth, GeoJSON Importer, Engine Keputusan AHP-TOPSIS, Aksesibilitas WCAG AA, dan Optimasi Bundle) telah lulus 130 pengujian otomatis backend, verifikasi browser E2E, serta linting dan build frontend bersih. Lihat [docs/PROJECT_STATUS.md](docs/PROJECT_STATUS.md).

---

## 🚀 Fitur Utama

1. **Pelaporan Kerusakan Jalan Berbasis Geospasial:**
   - Unggah foto kerusakan dengan validasi MIME/ekstensi aman dan proteksi token Bearer.
   - Penentuan koordinat interaktif melalui klik peta atau GPS perangkat.
   - Deteksi potensi laporan duplikat berbasis radius jarak geospasial.
   - Perlindungan draf formulir pelaporan di sesi browser.
2. **WebGIS Interaktif & Visualisasi Choropleth:**
   - Pemetaan ruas jalan dan titik kerusakan menggunakan Leaflet.
   - Pewarnaan tematik ruas jalan resmi berdasarkan kepadatan laporan kerusakan terverifikasi (merah $\ge 3$, oranye 1–2, biru 0) dan aksen ranking hasil TOPSIS.
   - Isolasi visual segmen demo/simulasi dengan garis putus-putus amber (`dashArray: '6, 6'`).
   - Popup informatif lengkap dengan status provenance `[DATA RESMI]` vs `[DATA DEMO]`, panjang ruas, fungsi jalan, dan skor preferensi TOPSIS.
3. **Alur Kerja Verifikasi & Asesmen Lapangan:**
   - Transisi status laporan berjenjang: `submitted` $\to$ `under_review` $\to$ `verified` / `needs_info` / `rejected` $\to$ `resolved`.
   - Pencatatan asesmen teknis lapangan berbasis protokol `SDI-ADAPTED-1.0` (skala berjangkar 1–5 untuk kriteria K1 s.d. K4).
4. **Sistem Pendukung Keputusan (AHP-TOPSIS):**
   - **AHP (Analytic Hierarchy Process):** Pembobotan 7 kriteria keputusan ($K_1$--$K_7$) dengan validasi rasio konsistensi ($\text{CR} \le 0{,}10$). Matriks tidak konsisten ditolak untuk aktivasi resmi.
   - **TOPSIS (Technique for Order Preference by Similarity to Ideal Solution):** Perangkingan ruas jalan alternatif berdasarkan kedekatan relatif terhadap solusi ideal positif ($A^+$) dan solusi ideal negatif ($A^-$).
   - **Sensitivitas & Audit Snapshot:** Analisis sensitivitas bobot (*One-At-a-Time* & *Equal Weights*) serta pencatatan snapshot input hash untuk menjamin reproduktifitas perhitungan ilmiah.
5. **Kontrol Akses Berbasis Peran (RBAC):**
   - 4 Peran resmi: `reporter` (warga), `officer` (petugas survei), `expert` (pakar AHP), dan `admin` (administrator sistem).
   - Pencegahan IDOR, proteksi privasi pelapor, dan penonaktifan akun secara instan.
6. **Aksesibilitas & Performa Frontend:**
   - Memenuhi standar WCAG AA (rasio kontras $\ge 4{,}5:1$, pola tab WAI-ARIA dengan navigasi panah keyboard, *focus trapping* dan tombol *Escape* pada modal dialog).
   - Debounce pencarian kode laporan 300 ms dengan indikator `(mengetik…)` dan integrasi `AbortController`.
   - Pemisahan bundle (*code-splitting*) via `React.lazy` dan isolasi chunk Leaflet (`rollupOptions.manualChunks`).

---

## 🛠️ Stack Teknologi

| Lapisan | Teknologi & Versi |
|---|---|
| **Backend** | Laravel 9.52.22 (PHP 8.0.30), Laravel Sanctum, PHPUnit |
| **Frontend** | React 19, Vite 8, React-Leaflet, Oxlint |
| **Database** | MariaDB 10.4.32 (XAMPP), MySQL Driver |
| **Pemetaan** | Leaflet 1.9+, OpenStreetMap Basemap, GeoJSON |

---

## 📋 Persyaratan Lingkungan

- PHP $\ge 8.0$ dengan ekstensi `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `fileinfo`.
- Composer 2.x
- Node.js $\ge 18$ & npm $\ge 9$
- XAMPP (Apache & MariaDB/MySQL)
- Git

Panduan instalasi Windows lengkap dapat dilihat di [docs/SETUP_WINDOWS.md](docs/SETUP_WINDOWS.md).

---

## 💻 Panduan Menjalankan Aplikasi

### 1. Database (XAMPP)
1. Buka **XAMPP Control Panel** dan nyalakan modul **MySQL**.
2. Buka phpMyAdmin (`http://localhost/phpmyadmin/`) dan buat database bernama:
   - `roadpriority` (untuk database pengembangan/aplikasi)
   - `roadpriority_test` (khusus pengujian otomatis)

### 2. Backend (Terminal 1)
```powershell
cd backend
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan db:seed --class=DemoSimulationSeeder
php artisan serve --host=127.0.0.1 --port=8000
```

### 3. Frontend (Terminal 2)
```powershell
cd frontend
npm install
npm run dev
```
Akses aplikasi melalui browser di **`http://localhost:5173`**.

---

## 🔑 Kredensial Pengujian (Login)

Seluruh akun default menggunakan password: **`ChangeMe123!`**

| Role | Email | Password | Deskripsi Akses |
|---|---|---|---|
| **Admin** | `admin@roadpriority.local` | `ChangeMe123!` | Akses penuh ke seluruh tab, manajemen pengguna, aktivasi bobot AHP, dan eksekusi resmi TOPSIS. |
| **Petugas (Officer)** | `officer@roadpriority.local` | `ChangeMe123!` | Antrean verifikasi laporan kerusakan warga dan input asesmen teknis lapangan. |
| **Pakar (Expert)** | `expert@roadpriority.local` | `ChangeMe123!` | Pengisian matriks perbandingan berpasangan kriteria AHP. |
| **Pelapor (Warga)** | `reporter@roadpriority.local` | `ChangeMe123!` | Pembuatan laporan kerusakan jalan baru dan pemantauan status laporan mandiri. |

---

## 🧪 Pengujian Otomatis

Jalankan pengujian backend dan frontend:

```powershell
# 1. Backend PHPUnit Suite (130 pengujian)
cd backend
php artisan test

# 2. Frontend Linting & Build Produksi
cd ../frontend
npm run lint
npm run build
```

---

## 📚 Dokumentasi Proyek

- [docs/PROJECT_STATUS.md](docs/PROJECT_STATUS.md): Laporan status proyek terkini dan matriks hasil pengujian.
- [docs/PRD_06_3_PERFORMANCE_ACCESSIBILITY_REPORT.md](docs/PRD_06_3_PERFORMANCE_ACCESSIBILITY_REPORT.md): Laporan optimasi performa, aksesibilitas WAI-ARIA, dan WebGIS choropleth.
- [docs/PRD_06_2_UI_FOUNDATION_REPORT.md](docs/PRD_06_2_UI_FOUNDATION_REPORT.md): Laporan standarisasi UI foundation, sinkronisasi URL, dan responsivitas.
- [docs/ACCESS_CONTROL.md](docs/ACCESS_CONTROL.md): Matriks hak akses peran (RBAC) dan kebijakan keamanan (anti-IDOR).
- [docs/RESEARCH_DATA_COLLECTION_PROTOCOL.md](docs/RESEARCH_DATA_COLLECTION_PROTOCOL.md): Protokol survei kondisi jalan lapangan dan elisitasi AHP pakar.
- [docs/RESEARCH_DATA_DICTIONARY.md](docs/RESEARCH_DATA_DICTIONARY.md): Kamus data penelitian dan pemetaan skema.
- [docs/API_CONTRACT.md](docs/API_CONTRACT.md): Spesifikasi kontrak REST API.
- [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md): Arsitektur sistem dan diagram aliran data.
- [docs/SETUP_WINDOWS.md](docs/SETUP_WINDOWS.md): Panduan konfigurasi lingkungan pengembangan lokal Windows + XAMPP.

---

## 📄 Lisensi

Proyek Sistem Informasi RoadPriority dikembangkan untuk tujuan akademik/penelitian.

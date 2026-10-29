# Audit Proyek & Lingkungan Teknis (`PROJECT_AUDIT.md`)

**Tanggal audit:** 29 Oktober 2026  
**Ruang lingkup:** Master PRD v2.0 — PRD 01 Bagian A (Audit Repository & Lingkungan Teknis)  
**Status:** Selesai diverifikasi pada lingkungan lokal pengguna.

---

## 1. Kondisi Awal & Baseline Repository

1. **Kondisi awal sebelum PRD 01:** direktori kerja `c:\Users\MyBook Hype AMD\OneDrive\Documents\ML` kosong (0 berkas, bukan repository Git).
2. **Kondisi setelah PRD 01 Bootstrap (tag `prd-01-done`, commit `f339fee`) dan implementasi mesin keputusan (`prd-05-ahp-topsis-engine`):**
   - Monorepo Git di `c:\Users\MyBook Hype AMD\OneDrive\Documents\ML\RoadPriority`.
   - `backend/`: Laravel Framework 9.52.22 (PHP 8.0.30).
   - `frontend/`: React 19.3.1 + Vite 8.3.5.
   - `docs/`: dokumentasi arsitektur, setup, kontrak API, keputusan, dan audit.

### Hasil Baseline Test & Build Aktual

| Komponen | Perintah | Hasil Aktual | Waktu |
|---|---|---|---|
| Backend test (awal PRD 01) | `php artisan test` | `4 passed` (2 bawaan + 2 `HealthEndpointTest`) | 0,33 s |
| Backend test (dengan mesin AHP-TOPSIS) | `php artisan test` | `80 passed` (`AhpServiceTest`, `TopsisServiceTest`, `SensitivityServiceTest`, `SnapshotServiceTest`, `HealthEndpointTest`, bawaan) | 0,39 s |
| Backend migration | `php artisan migrate:status` | 4 migration bawaan berstatus `[1] Ran` pada database `roadpriority` | - |
| Frontend linter | `npm run lint` (`oxlint`) | `Found 0 warnings and 0 errors` (8 berkas, 104 aturan) | 16 ms |
| Frontend production build | `npm run build` (`vite build`) | `built in 151ms` (`dist/index.html`, CSS, JS bundle) | 151 ms |

---

## 2. Spesifikasi Lingkungan & Kemampuan Spasial Database

| Komponen | Versi Aktual | Catatan Audit |
|---|---|---|
| OS | Windows (64-bit) | Shell PowerShell 5.1 |
| PHP | `8.0.30` (cli, ZTS, Visual C++ 2019 x64, `C:\xampp\php\php.exe`) | Ekstensi aktif: `bcmath`, `ctype`, `curl`, `fileinfo`, `json`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, `zip`. Sudah melewati masa dukungan aktif (lihat DEC-002). |
| Composer | `2.8.12` (2025-09-19) | Berfungsi normal. |
| Node.js / npm | `v24.16.0` / `11.13.0` | `npm audit` pada `frontend/` melaporkan `0 vulnerabilities`. |
| Git | `2.51.1.windows.1` | Aktif. |
| Python (alat uji referensi) | `3.12.6` | Dipakai untuk skrip verifikasi independen `ahp_topsis_reference.py` (stdlib murni). |
| Database Server | `10.4.32-MariaDB` (`C:\xampp\mysql\bin\mysqld.exe`) | Diakses lewat driver `mysql` Laravel pada `127.0.0.1:3306`, database `roadpriority`. |

### Temuan Penting: Verifikasi Fungsi Spasial MariaDB 10.4.32

Sesuai amanat Master PRD §9 & §11 (`[PERLU VERIFIKASI di PRD 01]`), dilakukan pengujian langsung terhadap fungsi spasial MariaDB 10.4.32:

1. **Tipe geometri dan konstruktor:** `POINT`, `LINESTRING`, `POLYGON`, dan `ST_GeomFromText(..., 4326)` didukung dan berjalan normal.
2. **Urutan sumbu pada `ST_Distance_Sphere(POINT, POINT)`:** pengujian `ST_Distance_Sphere(Point(106.6527, -6.3016), Point(106.6537, -6.3016))` menghasilkan **`110.52` meter** (pergeseran bujur $0{,}001^\circ$ di sekitar lintang $-6{,}30^\circ$). Ini membuktikan urutan argumen adalah **`Point(longitude, latitude)`**.
3. **Keterbatasan `ST_Distance_Sphere` pada `LINESTRING`:** pemanggilan `ST_Distance_Sphere(POINT, LINESTRING)` pada MariaDB 10.4.32 menghasilkan **`ERROR 1815 (HY000): Internal error: st_distance_sphere`**, sedangkan `ST_Distance(POINT, LINESTRING)` berhasil namun mengembalikan jarak kartesian dalam derajat (`0.0006` derajat), bukan meter.
4. **Implikasi arsitektur untuk PRD 02 & PRD 04 (map-matching laporan ke ruas jalan):**
   - Kolom geometri tetap menggunakan `POINT` dan `LINESTRING` dengan `SPATIAL INDEX` untuk penyaringan kotak pembatas cepat (`MBRIntersects`).
   - Perhitungan jarak presisi dalam meter dari titik laporan ke segmen garis (`segment_match_distance_m`) **wajib dihitung di service layer PHP** (proyeksi equirectangular lokal / haversine point-to-segment terhadap kandidat ruas hasil filter bounding box), **bukan** lewat `ST_Distance_Sphere(POINT, LINESTRING)` di SQL.


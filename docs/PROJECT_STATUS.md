# PROJECT_STATUS — RoadPriority

Diperbarui: 2026-10-29 23:59 WIB oleh sesi agen (Antigravity)

## Tahap aktif

- **PRD 01 (Foundation & Project Bootstrap + Audit Sumber Data):** **selesai dan disetujui**.
- **PRD 02 (Database Design & API Contract):** **selesai dan terverifikasi** (`prd-02-database-and-api`).
- **PRD 03 (Backend API, Authentication & Access Control):** **selesai dan terverifikasi** (`prd-03-backend-auth-rbac`).
- **PRD 04 (Road Damage Reporting & WebGIS):** **selesai diimplementasikan dan diverifikasi** pada cabang `prd-04-reporting-and-webgis` (menunggu persetujuan penutupan tahap dari pengguna).
- **PRD 05 (Mesin Keputusan Inti AHP-TOPSIS — Pure Service Layer):** **pure service layer selesai diimplementasikan dan teruji unit** (`80` unit test mesin keputusan lulus; integrasi endpoint HTTP AHP-TOPSIS siap dikerjakan pada tahap PRD 05 berikutnya).

## Ringkasan kondisi

1. **Modul Pelaporan Kerusakan Jalan & Foto Bukti Aman (PRD 04):**
   - `POST /api/reports` menghasilkan kode unik server `RPT-YYYYMMDD-XXXXXX` (`DamageReportService`), mengabaikan spoofing `report_code`/`status`/`reporter_id`, mengizinkan laporan tersimpan tanpa `road_segment_id`, serta memvalidasi isi biner foto (`JPEG`, `PNG`, `WebP` maks 5 file @ 5 MB) menggunakan `getimagesize()`.
   - Foto disimpan di disk privat `local` (`storage/app/report-photos/...` di luar `public/`), dilengkapi *orphan file cleanup* otomatis jika transaksi database gagal, dan hanya dapat diakses melalui endpoint terotorisasi `GET /api/reports/{damageReport}/photos/{reportPhoto}`.
2. **Alur Verifikasi Petugas, Deteksi Kandidat Duplikat & Koreksi Berjejak Histori:**
   - `ReportStatusService` mendukung transisi `submitted → under_review → verified | rejected | duplicate | needs_info` dan `verified → resolved | under_review` (koreksi berjejak histori tanpa menghapus riwayat sebelumnya).
   - `GeoGeometryService` menghitung jarak titik-ke-garis (`LineString` GeoJSON `[lng, lat]`) dan jarak antar-titik dalam satuan meter untuk menyajikan `suggested_segments` ($\le 50\text{ m}$) dan `nearby_duplicate_candidates` ($\le 50\text{ m}$, $\pm 30\text{ hari}$) pada panel verifikasi petugas.
3. **WebGIS Interaktif Leaflet + OpenStreetMap:**
   - Frontend React + Vite + Leaflet (`WebGisMap.jsx`, `ReportForm.jsx`, `ReportDetailModal.jsx`, `HomePage.jsx`) menyediakan peta laporan publik, overlay geometri `road_segments`, *spatial grid clustering* pada zoom $< 15$, sinkronisasi daftar $\leftrightarrow$ peta, filter `status`/`damage_type`/periode/`q`/`bbox` (debounce 350 ms + `AbortController`), pemilihan lokasi dengan GPS perangkat atau klik/geser marker manual, antrean verifikasi petugas, dan modal detail laporan.

## Hasil build dan tes terakhir

| Perintah | Hasil | Tanggal |
|---|---|---|
| `php artisan test` (pada `roadpriority_test`) | **`110 passed` (0 failed)** dalam `6.64s` (`DamageReportingAndWebGisTest` 6 tes, `AuthAndAccessControlTest` 16 tes, `DatabaseSchemaIntegrityTest` 8 tes, `HealthEndpointTest` 2 tes, `AhpServiceTest`, `TopsisServiceTest`, `SensitivityServiceTest`, `SnapshotServiceTest`, `ExampleTest`) | 2026-10-29 23:55 WIB |
| `php artisan route:list --path=api` | 17 rute API terdaftar (`api/health`, `api/login`, `api/logout`, `api/me`, `api/reports`, `api/reports/{reportIdentifier}`, `api/reports/{damageReport}/status`, `api/reports/{damageReport}/photos/{reportPhoto}`, `api/road-segments`, `api/road-segments/{segmentIdentifier}`, `api/criteria`, `api/users`, `api/audit-logs`) | 2026-10-29 23:52 WIB |
| `npm run lint` (`frontend/`) | `Found 0 warnings and 0 errors` (12 berkas) | 2026-10-29 23:58 WIB |
| `npm run build` (`frontend/`) | `✓ built in 353ms` (`dist/assets/index-BJzjyRV0.js` 408.50 kB, `index-BK8-Xho-.css` 24.27 kB) | 2026-10-29 23:58 WIB |

## Requirement dan acceptance criteria PRD 04

| Kriteria (PRD 04 §15) | Status | Bukti |
|---|---|---|
| Pengguna dapat membuat laporan dengan koordinat yang valid | **Terpenuhi** | `StoreDamageReportRequest`, `DamageReportService`, `ReportForm.jsx`, `DamageReportingAndWebGisTest` |
| Foto dapat disimpan dan ditampilkan sesuai izin akses | **Terpenuhi** | `ReportAccessController@showPhoto`, `DamageReportPolicy@viewPhoto`, `test_photo_and_report_visibility_enforced_across_guest_reporter_and_officer_with_correction_trail` |
| Kode laporan unik dan histori status tercatat | **Terpenuhi** | `DamageReportService::generateUniqueReportCode`, `ReportStatusHistory`, `test_reporter_can_submit_report_without_segment_with_valid_photo_and_server_generates_unique_code` |
| Petugas dapat memverifikasi laporan melalui alur yang sah | **Terpenuhi** | `ReportStatusService::transition`, `ReportDetailModal.jsx`, uji verifikasi & duplikat |
| Pengunjung hanya melihat data yang memang boleh dipublikasikan | **Terpenuhi** | `ReportAccessController@index` & `@show`, [`docs/DATA_VISIBILITY.md`](DATA_VISIBILITY.md) |
| WebGIS menampilkan laporan yang sesuai filter | **Terpenuhi** | `WebGisMap.jsx`, `HomePage.jsx`, `test_public_reports_and_road_segments_support_filters_bbox_pagination_and_exclude_demo_data` |
| Daftar dan peta tersinkronisasi | **Terpenuhi** | `selectedReportId` & `onBoundsChange` di `HomePage.jsx` + `WebGisMap.jsx` |
| Pagination atau pembatasan hasil mencegah pemuatan data tanpa batas | **Terpenuhi** | `per_page` maks `100` di `ListDamageReportsRequest` & `ListRoadSegmentsRequest` |
| Otorisasi diuji pada backend | **Terpenuhi** | `AuthAndAccessControlTest` (16 tes) + `DamageReportingAndWebGisTest` (6 tes) |
| Test utama lulus | **Terpenuhi** | `110 passed` pada `roadpriority_test`, `0 warnings / 0 errors` pada `npm run lint` |
| Dokumentasi dan `PROJECT_STATUS.md` diperbarui | **Terpenuhi** | [`docs/REPORTING_MODULE.md`](REPORTING_MODULE.md), [`docs/WEBGIS.md`](WEBGIS.md), [`docs/DATA_VISIBILITY.md`](DATA_VISIBILITY.md), [`docs/API_CONTRACT.md`](API_CONTRACT.md), dan dokumen ini |
| Tidak ada data demonstrasi yang tercampur tanpa penandaan dengan data penelitian | **Terpenuhi** | `where('is_demo', false)` diterapkan secara ketat pada seluruh query publik `reports` dan `road_segments` |

## Issue terbuka & Batasan Data

| ID | Keparahan | Deskripsi | Langkah reproduksi / Mitigasi |
|---|---|---|---|
| ISS-01 | Sedang | PHP 8.0.30 & Laravel 9.52.22 memiliki 19 advisory keamanan pada `composer audit` (`DEC-002`) | Aman untuk pengembangan lokal; wajib upgrade PHP $\ge 8.2$ sebelum deployment publik |
| ISS-02 | Rendah | Poligon batas administratif resmi BIG (`boundary_geojson`) dan ekstrak penuh jalan OSM BSD belum dimuat ke database produksi (`GAP-02`, `GAP-03`) | Sistem saat ini menggunakan kotak pembatas referensi BSD/Tangerang (`config/reporting.php`) dengan penanda peringatan eksplisit dan mengizinkan laporan tanpa `road_segment_id` hingga dataset poligon/ruas jalan resmi diimpor |

## Langkah berikutnya yang disarankan

1. Setujui penutupan **PRD 04**.
2. Lanjutkan ke penyelesaian **PRD 05 — Integrasi Endpoint HTTP & Antarmuka Mesin Keputusan AHP-TOPSIS** (menghubungkan model Eloquent `DecisionCriterion`, `CriterionWeightVersion`, `DamageAssessment`, dan `DecisionRun` ke service layer `AhpService`, `TopsisService`, `SensitivityService`, dan `SnapshotService` yang sudah teruji unit).

# PROJECT_STATUS — RoadPriority

Diperbarui: 2026-10-29 19:38 WIB oleh sesi agen (Antigravity)

## Tahap aktif

- **PRD 01 (Foundation & Project Bootstrap + Audit Sumber Data):** **selesai dan disetujui**.
- **PRD 02 (Database Design & API Contract):** **selesai diimplementasikan dan diverifikasi** pada cabang `prd-02-database-and-api` (menunggu persetujuan penutupan tahap dari pengguna sebelum mulai PRD 03).
- **PRD 05 (Mesin Keputusan Inti AHP-TOPSIS — Pure Service Layer):** **selesai diimplementasikan dan teruji unit** (`80` unit test mesin keputusan lulus).

## Ringkasan kondisi

1. **Database & Skema (PRD 02):** 12 berkas migration baru (total 16 migration bersama bawaan Laravel) membentuk 14 tabel inti (`users`, `administrative_areas`, `road_segments`, `damage_reports`, `report_photos`, `report_status_histories`, `damage_assessments`, `decision_criteria`, `criterion_weight_versions`, `criterion_weight_items`, `ahp_pairwise_comparisons`, `decision_runs`, `decision_run_items`, `audit_logs`).
2. **Model Eloquent & Integritas:** 14 model Eloquent dengan proteksi *mass assignment*, relasi lengkap, validasi domain (`DamageAssessment` wajib memiliki segmen atau laporan), serta aturan foreign key yang melindungi histori (`RESTRICT` untuk induk histori/keputusan, `SET NULL` untuk aktor `users`).
3. **Database Pengujian Terpisah:** `backend/phpunit.xml` menggunakan database khusus **`roadpriority_test`**, sehingga pengujian otomatis (`RefreshDatabase`) tidak pernah menghapus atau mengubah isi database development **`roadpriority`**.
4. **Kontrak API & Endpoint Health:** `docs/API_CONTRACT.md`, `docs/ERD.md`, `docs/DATABASE_SCHEMA.md`, dan `docs/DATA_DICTIONARY.md` telah selaras penuh. Endpoint `GET /api/health` tetap aktif dan lulus uji.

## Hasil build dan tes terakhir

| Perintah | Hasil | Tanggal |
|---|---|---|
| `php artisan migrate` (pada `roadpriority`) | 16 migration berstatus `Ran` (4 bawaan + 12 migration PRD 02) | 2026-10-29 19:31 WIB |
| `php artisan migrate:rollback --step=12 && php artisan migrate` | Rollback dan re-migrate 12 migration PRD 02 berhasil tanpa error | 2026-10-29 19:31 WIB |
| `php artisan test` (pada `roadpriority_test`) | **`88 passed` (0 failed)** dalam `2.40s` (`DatabaseSchemaIntegrityTest`, `HealthEndpointTest`, `AhpServiceTest`, `TopsisServiceTest`, `SensitivityServiceTest`, `SnapshotServiceTest`, `ExampleTest`) | 2026-10-29 19:35 WIB |
| `npm run lint` (`frontend/`) | `Found 0 warnings and 0 errors` | 2026-10-29 19:24 WIB |
| `npm run build` (`frontend/`) | `built in 151ms` | 2026-10-29 19:03 WIB |

## Requirement dan acceptance criteria PRD 02

| Kriteria (PRD 02 §13) | Status | Bukti |
|---|---|---|
| ERD sesuai dengan model data aktual | **Terpenuhi** | [`docs/ERD.md`](ERD.md) |
| Seluruh entitas inti memiliki definisi kolom dan relasi | **Terpenuhi** | [`docs/DATABASE_SCHEMA.md`](DATABASE_SCHEMA.md), [`docs/DATA_DICTIONARY.md`](DATA_DICTIONARY.md), 14 model di `backend/app/Models/` |
| Migration dapat dijalankan pada database pengujian kosong | **Terpenuhi** | `DatabaseSchemaIntegrityTest::test_test_suite_uses_dedicated_test_database_and_migrates_all_tables` (`roadpriority_test`) |
| Foreign key, unique index, dan nullable field telah diverifikasi | **Terpenuhi** | `DatabaseSchemaIntegrityTest` (8 skenario integritas skema lulus) |
| Model Eloquent memiliki relasi yang sesuai | **Terpenuhi** | `DatabaseSchemaIntegrityTest::test_nullable_fields_and_core_eloquent_relationships_work` & `test_decision_run_links_to_weight_version_and_enforces_unique_segment_and_rank` |
| API contract mendokumentasikan endpoint dan format respons | **Terpenuhi** | [`docs/API_CONTRACT.md`](API_CONTRACT.md) |
| Keputusan geospasial telah ditetapkan dan didokumentasikan | **Terpenuhi** | `DEC-008` & `DEC-010` di [`docs/DECISIONS.md`](DECISIONS.md) serta [`docs/DATABASE_SCHEMA.md`](DATABASE_SCHEMA.md) §1.2 |
| Data histori keputusan dapat ditelusuri ke versi bobot dan snapshot input | **Terpenuhi** | `DecisionRun`, `DecisionRunItem`, `CriterionWeightVersion`, `SnapshotServiceTest`, dan `DatabaseSchemaIntegrityTest` |
| Endpoint health tetap bekerja | **Terpenuhi** | `HealthEndpointTest` (2 tes lulus) |
| Tidak ada controller palsu atau data fiktif yang disajikan sebagai implementasi nyata | **Terpenuhi** | Hanya `HealthController` yang ada di `app/Http/Controllers/Api/`; data seeder contoh berada di `DemoSimulationSeeder` dengan label eksplisit `is_demo = true` dan `data_origin = 'demo'` |
| Test report dan `PROJECT_STATUS.md` diperbarui | **Terpenuhi** | Dokumen ini |

## Perubahan database dan endpoint terakhir

- **Migration baru (`backend/database/migrations/`):** `2026_10_29_000001` s.d. `2026_10_29_000012` (12 berkas migration).
- **Model Eloquent (`backend/app/Models/`):** `User`, `AdministrativeArea`, `RoadSegment`, `DamageReport`, `ReportPhoto`, `ReportStatusHistory`, `DamageAssessment`, `DecisionCriterion`, `CriterionWeightVersion`, `CriterionWeightItem`, `AhpPairwiseComparison`, `DecisionRun`, `DecisionRunItem`, `AuditLog`.
- **Seeder (`backend/database/seeders/`):** `CandidateCriteriaSeeder`, `DemoSimulationSeeder`.
- **Endpoint aktif:** `GET /api/health` (endpoint lain di `docs/API_CONTRACT.md` berstatus `planned` untuk PRD 03–06).

## Issue terbuka

| ID | Keparahan | Deskripsi | Langkah reproduksi / Mitigasi |
|---|---|---|---|
| ISS-01 | Sedang | PHP 8.0.30 & Laravel 9.52.22 memiliki 19 advisory keamanan pada `composer audit` (`DEC-002`) | Aman untuk pengembangan lokal; wajib upgrade PHP $\ge 8.2$ sebelum deployment publik |
| ISS-02 | Rendah | `ST_Distance_Sphere(POINT, LINESTRING)` menghasilkan `ERROR 1815` pada MariaDB 10.4.32 (`DEC-008`, `DEC-010`) | Telah dimitigasi dengan penyimpanan `geometry_geojson` + indeks centroid `DECIMAL(10,7)` dan perhitungan jarak titik-ke-garis di PHP pada PRD 04 |

## Keputusan menunggu persetujuan

- Konfirmasi nilai spesifik untuk **D-02** (daftar kecamatan/kelurahan wilayah studi BSD), **D-05** (kebijakan verifikasi email & rate limit pelapor untuk PRD 03), dan **D-06** (pilihan perlakuan kriteria K4). Lihat [`docs/DECISIONS.md`](DECISIONS.md) §2.

## Langkah berikutnya yang disarankan

1. Setujui penutupan **PRD 02**.
2. Lanjutkan ke **PRD 03 — Backend, Autentikasi, dan Hak Akses** (implementasi autentikasi Sanctum, Policy per peran `reporter`/`officer`/`expert`/`admin`, Form Request validation, handler format error konsisten, audit logger service, rate limiting, serta matriks feature test peran $\times$ endpoint dan uji IDOR).

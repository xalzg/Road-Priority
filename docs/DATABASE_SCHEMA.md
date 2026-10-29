# Spesifikasi Skema Database (`DATABASE_SCHEMA.md`)

**Tahap:** PRD 02 — Database Design & API Contract  
**DBMS:** MariaDB 10.4.32 / MySQL (driver `mysql` Laravel, charset `utf8mb4`, collation `utf8mb4_unicode_ci`)  
**Lokasi migration:** `backend/database/migrations/`  
**Lokasi model Eloquent:** `backend/app/Models/`

---

## 1. Keputusan Penting Desain Skema

### 1.1 Peran Pengguna (`users.role`)
Sesuai PRD 02 §4.2 (*"Pilih desain yang paling sederhana selama kebutuhan sistem masih dapat dipenuhi"*), digunakan satu kolom `role` (`VARCHAR(32)`, default `'reporter'`) dan `is_active` (`BOOLEAN`, default `true`) pada tabel `users` tanpa menambahkan library RBAC eksternal. Nilai peran yang valid didefinisikan pada `App\Models\User::ROLES`:
- `reporter` (Pelapor)
- `officer` (Petugas/Verifikator)
- `expert` (Pakar AHP)
- `admin` (Administrator)

Untuk mencegah eskalasi hak akses melalui *mass assignment* (NFR-02, PRD 02 §10), `role` dan `is_active` tidak dimasukkan ke dalam `User::$fillable`.

### 1.2 Strategi Penyimpanan Geospasial (PRD 02 §4.4)
Berdasarkan hasil audit teknis PRD 01 pada MariaDB 10.4.32 (`DEC-008` & ` DEC-010`):
1. **Titik laporan (`damage_reports`):** disimpan secara eksplisit menggunakan `latitude` (`DECIMAL(10,7)`) dan `longitude` (`DECIMAL(10,7)`) dalam sistem referensi **WGS 84 (EPSG:4326)** dengan indeks komposit `idx_damage_reports_coordinates (latitude, longitude)`.
2. **Geometri garis ruas jalan (`road_segments`):**
   - Disimpan sebagai objek GeoJSON `LineString` tervalidasi pada kolom `geometry_geojson` (`JSON`) dengan urutan koordinat standar GeoJSON **`[longitude, latitude]`** (WGS 84 / EPSG:4326), sehingga dapat langsung dirender oleh Leaflet tanpa konversi biner WKB yang rawan perbedaan urutan sumbu antar-versi MySQL/MariaDB.
   - Dilengkapi koordinat titik tengah `centroid_latitude` (`DECIMAL(10,7)`) dan `centroid_longitude` (`DECIMAL(10,7)`) berindeks `idx_road_segments_centroid` untuk penyaringan *bounding box* (`bbox`) cepat di database.
   - Jarak titik laporan ke garis ruas jalan (`segment_match_distance_m`) dihitung dalam satuan meter pada service layer PHP terhadap koordinat `geometry_geojson`.

### 1.3 Ketertelusuran Bobot AHP & Snapshot Keputusan (PRD 02 §4.10–4.12)
1. Setiap `criterion_weight_versions` menyimpan hasil uji konsistensi ($\lambda_{\max}$, `consistency_index`, `consistency_ratio`, `is_consistent`) dengan presisi `DECIMAL(18,12)`, rincian bobot per kriteria di `criterion_weight_items`, serta matriks perbandingan berpasangan pakar di `ahp_pairwise_comparisons`.
2. Setiap `decision_runs` wajib menunjuk ke `criterion_weight_version_id` (`RESTRICT ON DELETE`), mencatat `algorithm_name`, `algorithm_version`, `input_hash` (SHA-256 kanonik 64 karakter), dan membekukan konfigurasi di `input_snapshot_reference` (`JSON`).
3. Setiap baris alternatif pada `decision_run_items` menyimpan `rank_position` (`INT UNSIGNED`), `preference_score` ($C_i$, `DECIMAL(18,12)`), `d_plus`, `d_minus`, `largest_driving_criterion`, serta `input_values_snapshot` dan `intermediate_values_snapshot` (`JSON`).
4. Constraint unik ganda pada `decision_run_items`:
   - `uq_run_road_segment (decision_run_id, road_segment_id)` memastikan satu ruas jalan hanya muncul satu kali pada satu run.
   - `uq_run_rank_position (decision_run_id, rank_position)` memastikan tidak ada dua ruas yang menduduki peringkat sama pada satu run (baris yang dikeluarkan karena data hilang memiliki `is_included = false` dan `rank_position = NULL`).

---

## 2. Daftar Urutan Migration

| Urutan | Berkas Migration | Tabel yang Dibuat / Diubah |
|---|---|---|
| 1–4 | `2014_10_12_*` s.d. `2019_12_14_*` (bawaan Laravel) | `users`, `password_resets`, `failed_jobs`, `personal_access_tokens` |
| 5 | [`2026_10_29_000001_add_role_and_status_to_users_table.php`](file:///c:/Users/MyBook%20Hype%20AMD/OneDrive/Documents/ML/RoadPriority/backend/database/migrations/2026_10_29_000001_add_role_and_status_to_users_table.php) | Menambah `role`, `is_active`, dan indeks pada `users` |
| 6 | [`2026_10_29_000002_create_administrative_areas_table.php`](file:///c:/Users/MyBook%20Hype%20AMD/OneDrive/Documents/ML/RoadPriority/backend/database/migrations/2026_10_29_000002_create_administrative_areas_table.php) | `administrative_areas` |
| 7 | [`2026_10_29_000003_create_road_segments_table.php`](file:///c:/Users/MyBook%20Hype%20AMD/OneDrive/Documents/ML/RoadPriority/backend/database/migrations/2026_10_29_000003_create_road_segments_table.php) | `road_segments` |
| 8 | [`2026_10_29_000004_create_damage_reports_table.php`](file:///c:/Users/MyBook%20Hype%20AMD/OneDrive/Documents/ML/RoadPriority/backend/database/migrations/2026_10_29_000004_create_damage_reports_table.php) | `damage_reports` |
| 9 | [`2026_10_29_000005_create_report_photos_table.php`](file:///c:/Users/MyBook%20Hype%20AMD/OneDrive/Documents/ML/RoadPriority/backend/database/migrations/2026_10_29_000005_create_report_photos_table.php) | `report_photos` |
| 10 | [`2026_10_29_000006_create_report_status_histories_table.php`](file:///c:/Users/MyBook%20Hype%20AMD/OneDrive/Documents/ML/RoadPriority/backend/database/migrations/2026_10_29_000006_create_report_status_histories_table.php) | `report_status_histories` |
| 11 | [`2026_10_29_000007_create_damage_assessments_table.php`](file:///c:/Users/MyBook%20Hype%20AMD/OneDrive/Documents/ML/RoadPriority/backend/database/migrations/2026_10_29_000007_create_damage_assessments_table.php) | `damage_assessments` |
| 12 | [`2026_10_29_000008_create_decision_criteria_table.php`](file:///c:/Users/MyBook%20Hype%20AMD/OneDrive/Documents/ML/RoadPriority/backend/database/migrations/2026_10_29_000008_create_decision_criteria_table.php) | `decision_criteria` |
| 13 | [`2026_10_29_000009_create_criterion_weight_versions_tables.php`](file:///c:/Users/MyBook%20Hype%20AMD/OneDrive/Documents/ML/RoadPriority/backend/database/migrations/2026_10_29_000009_create_criterion_weight_versions_tables.php) | `criterion_weight_versions`, `criterion_weight_items`, `ahp_pairwise_comparisons` |
| 14 | [`2026_10_29_000010_create_decision_runs_table.php`](file:///c:/Users/MyBook%20Hype%20AMD/OneDrive/Documents/ML/RoadPriority/backend/database/migrations/2026_10_29_000010_create_decision_runs_table.php) | `decision_runs` |
| 15 | [`2026_10_29_000011_create_decision_run_items_table.php`](file:///c:/Users/MyBook%20Hype%20AMD/OneDrive/Documents/ML/RoadPriority/backend/database/migrations/2026_10_29_000011_create_decision_run_items_table.php) | `decision_run_items` |
| 16 | [`2026_10_29_000012_create_audit_logs_table.php`](file:///c:/Users/MyBook%20Hype%20AMD/OneDrive/Documents/ML/RoadPriority/backend/database/migrations/2026_10_29_000012_create_audit_logs_table.php) | `audit_logs` |

---

## 3. Rincian Struktur Tabel

### 3.1 `users`
- **PK:** `id` (`BIGINT UNSIGNED AUTO_INCREMENT`)
- **Kolom:** `name` (`VARCHAR(255)`), `email` (`VARCHAR(255) UNIQUE`), `email_verified_at` (`TIMESTAMP NULL`), `password` (`VARCHAR(255)`), `role` (`VARCHAR(32) DEFAULT 'reporter'`), `is_active` (`TINYINT(1) DEFAULT 1`), `remember_token`, `created_at`, `updated_at`.
- **Indeks:** `users_email_unique`, `idx_users_role`, `idx_users_is_active`.

### 3.2 `administrative_areas`
- **PK:** `id` (`BIGINT UNSIGNED`)
- **Kolom:** `name` (`VARCHAR(191)`), `administrative_level` (`VARCHAR(32)`), `parent_id` (`BIGINT UNSIGNED NULL`), `external_code` (`VARCHAR(64) NULL`), `boundary_geojson` (`JSON NULL`), `is_study_area` (`TINYINT(1) DEFAULT 0`), `created_at`, `updated_at`.
- **Constraint & Indeks:** `uq_administrative_areas_external_code`, FK `parent_id` $\rightarrow$ `administrative_areas(id)` (`ON DELETE SET NULL`), `idx_admin_areas_level`, `idx_admin_areas_study_area`.

### 3.3 `road_segments`
- **PK:** `id` (`BIGINT UNSIGNED`)
- **Kolom:** `administrative_area_id` (`BIGINT UNSIGNED NULL`), `name` (`VARCHAR(191) NULL`), `road_reference_code` (`VARCHAR(64)`), `geometry_geojson` (`JSON NULL`), `centroid_latitude` (`DECIMAL(10,7) NULL`), `centroid_longitude` (`DECIMAL(10,7) NULL`), `length_meters` (`DECIMAL(10,2) NULL`), `road_function` (`VARCHAR(32) NULL`), `road_authority` (`VARCHAR(32) NULL`), `nearest_facility_meters` (`DECIMAL(10,2) NULL`), `source_name` (`VARCHAR(191) NULL`), `source_reference` (`VARCHAR(255) NULL`), `data_origin` (`VARCHAR(32) DEFAULT 'public_dataset'`), `is_demo` (`TINYINT(1) DEFAULT 0`), `is_active` (`TINYINT(1) DEFAULT 1`), `last_verified_at` (`TIMESTAMP NULL`), `created_at`, `updated_at`.
- **Constraint & Indeks:** `uq_road_segments_reference_code`, FK `administrative_area_id` $\rightarrow$ `administrative_areas(id)` (`ON DELETE SET NULL`), `idx_road_segments_centroid (centroid_latitude, centroid_longitude)`, `idx_road_segments_function`, `idx_road_segments_authority`, `idx_road_segments_active_demo`.

### 3.4 `damage_reports`
- **PK:** `id` (`BIGINT UNSIGNED`)
- **Kolom:** `report_code` (`VARCHAR(36)`), `reporter_id` (`BIGINT UNSIGNED NULL`), `road_segment_id` (`BIGINT UNSIGNED NULL`), `segment_match_distance_m` (`DECIMAL(8,2) NULL`), `damage_type` (`VARCHAR(64)`), `description` (`TEXT`), `latitude` (`DECIMAL(10,7)`), `longitude` (`DECIMAL(10,7)`), `gps_accuracy_m` (`DECIMAL(8,2) NULL`), `reported_at` (`TIMESTAMP`), `status` (`VARCHAR(32) DEFAULT 'submitted'`), `verification_notes` (`TEXT NULL`), `verified_by` (`BIGINT UNSIGNED NULL`), `verified_at` (`TIMESTAMP NULL`), `duplicate_of_id` (`BIGINT UNSIGNED NULL`), `data_origin` (`VARCHAR(32) DEFAULT 'citizen_report'`), `is_demo` (`TINYINT(1) DEFAULT 0`), `created_at`, `updated_at`.
- **Constraint & Indeks:** `uq_damage_reports_report_code`, FK `reporter_id`, `verified_by` $\rightarrow$ `users(id)` (`ON DELETE SET NULL`), FK `road_segment_id` $\rightarrow$ `road_segments(id)` (`ON DELETE SET NULL`), FK `duplicate_of_id` $\rightarrow$ `damage_reports(id)` (`ON DELETE SET NULL`), `idx_damage_reports_status_reported (status, reported_at)`, `idx_damage_reports_coordinates (latitude, longitude)`, `idx_damage_reports_damage_type`, `idx_damage_reports_is_demo`.

### 3.5 `report_photos`
- **PK:** `id` (`BIGINT UNSIGNED`)
- **Kolom:** `damage_report_id` (`BIGINT UNSIGNED`), `storage_disk` (`VARCHAR(32) DEFAULT 'local'`), `file_path` (`VARCHAR(255)`), `original_filename` (`VARCHAR(255) NULL`), `mime_type` (`VARCHAR(64)`), `file_size_bytes` (`BIGINT UNSIGNED`), `width` (`INT UNSIGNED NULL`), `height` (`INT UNSIGNED NULL`), `sha256` (`CHAR(64) NULL`), `is_public` (`TINYINT(1) DEFAULT 0`), `captured_at` (`TIMESTAMP NULL`), `created_at`, `updated_at`.
- **Constraint & Indeks:** FK `damage_report_id` $\rightarrow$ `damage_reports(id)` (`ON DELETE CASCADE`), `idx_report_photos_report_public (damage_report_id, is_public)`.

### 3.6 `report_status_histories`
- **PK:** `id` (`BIGINT UNSIGNED`)
- **Kolom:** `damage_report_id` (`BIGINT UNSIGNED`), `previous_status` (`VARCHAR(32) NULL`), `new_status` (`VARCHAR(32)`), `changed_by` (`BIGINT UNSIGNED NULL`), `notes` (`TEXT NULL`), `changed_at` (`TIMESTAMP`).
- **Constraint & Indeks:** FK `damage_report_id` $\rightarrow$ `damage_reports(id)` (**`ON DELETE RESTRICT`**), FK `changed_by` $\rightarrow$ `users(id)` (`ON DELETE SET NULL`), `idx_status_histories_report_changed (damage_report_id, changed_at)`.

### 3.7 `damage_assessments`
- **PK:** `id` (`BIGINT UNSIGNED`)
- **Kolom:** `road_segment_id` (`BIGINT UNSIGNED NULL`), `damage_report_id` (`BIGINT UNSIGNED NULL`), `assessed_by` (`BIGINT UNSIGNED NULL`), `assessment_date` (`TIMESTAMP`), `protocol_version` (`VARCHAR(32) DEFAULT 'SDI-ADAPTED-1.0'`), `severity_level` (`DECIMAL(8,4)`), `safety_risk_score` (`DECIMAL(8,4) NULL`), `road_function_score` (`DECIMAL(8,4) NULL`), `impact_score` (`DECIMAL(8,4) NULL`), `measurements` (`JSON NULL`), `status` (`VARCHAR(16) DEFAULT 'draft'`), `notes` (`TEXT NULL`), `assessment_source` (`VARCHAR(32) DEFAULT 'field_survey'`), `is_demo` (`TINYINT(1) DEFAULT 0`), `created_at`, `updated_at`.
- **Constraint & Indeks:** FK `road_segment_id` $\rightarrow$ `road_segments(id)` (**`ON DELETE RESTRICT`**), FK `damage_report_id` $\rightarrow$ `damage_reports(id)` (`ON DELETE SET NULL`), FK `assessed_by` $\rightarrow$ `users(id)` (`ON DELETE SET NULL`), validasi model mewajibkan minimal satu dari `road_segment_id` atau `damage_report_id` tidak bernilai `NULL`.

### 3.8 `decision_criteria`
- **PK:** `id` (`BIGINT UNSIGNED`)
- **Kolom:** `code` (`VARCHAR(32)`), `name` (`VARCHAR(191)`), `description` (`TEXT`), `data_type` (`VARCHAR(32)`), `unit` (`VARCHAR(64) NULL`), `criterion_type` (`VARCHAR(16)`: `'benefit'`/`'cost'`), `minimum_value` (`DECIMAL(14,4) NULL`), `maximum_value` (`DECIMAL(14,4) NULL`), `aggregation_rule` (`VARCHAR(191) NULL`), `is_required` (`TINYINT(1) DEFAULT 1`), `is_active` (`TINYINT(1) DEFAULT 1`), `created_at`, `updated_at`.
- **Constraint & Indeks:** `uq_decision_criteria_code (code)`, `idx_decision_criteria_active`, `idx_decision_criteria_type`.

### 3.9 `criterion_weight_versions`, `criterion_weight_items`, & `ahp_pairwise_comparisons`
- **`criterion_weight_versions`:** `id`, `version_name`, `status` (`draft`/`computed`/`approved`/`archived`), `criteria_count`, `lambda_max` (`DECIMAL(18,12)`), `consistency_index` (`DECIMAL(18,12)`), `consistency_ratio` (`DECIMAL(18,12)`), `is_consistent` (`TINYINT(1)`), `aggregation_method`, `created_by`, `approved_by`, `approved_at`, `created_at`, `updated_at`.
- **`criterion_weight_items`:** `id`, `criterion_weight_version_id` (`CASCADE`), `decision_criterion_id` (`RESTRICT`), `weight_value` (`DECIMAL(18,12)`), `created_at`, `updated_at`, dengan `uq_weight_version_criterion`.
- **`ahp_pairwise_comparisons`:** `id`, `criterion_weight_version_id` (`CASCADE`), `expert_id` (`SET NULL`), `criterion_a_id` (`RESTRICT`), `criterion_b_id` (`RESTRICT`), `comparison_value` (`DECIMAL(18,12)`), `created_at`, `updated_at`, dengan `uq_ahp_version_expert_pair`.

### 3.10 `decision_runs` & `decision_run_items`
- **`decision_runs`:** `id`, `run_code` (`VARCHAR(36) UNIQUE`), `name`, `criterion_weight_version_id` (**`RESTRICT`**), `algorithm_name`, `algorithm_version`, `status`, `is_simulation`, `input_hash` (`CHAR(64)`), `input_snapshot_reference` (`JSON`), `executed_by` (`SET NULL`), `executed_at`, `created_at`, `updated_at`.
- **`decision_run_items`:** `id`, `decision_run_id` (`CASCADE`), `road_segment_id` (**`RESTRICT`**), `is_included`, `exclusion_reason`, `rank_position` (`INT UNSIGNED NULL`), `preference_score` (`DECIMAL(18,12) NULL`), `d_plus` (`DECIMAL(18,12) NULL`), `d_minus` (`DECIMAL(18,12) NULL`), `largest_driving_criterion`, `input_values_snapshot` (`JSON`), `intermediate_values_snapshot` (`JSON NULL`), `created_at`, `updated_at`. Unique: `uq_run_road_segment` dan `uq_run_rank_position`.

### 3.11 `audit_logs`
- **PK:** `id` (`BIGINT UNSIGNED`)
- **Kolom:** `actor_id` (`BIGINT UNSIGNED NULL`, `SET NULL`), `action` (`VARCHAR(64)`), `entity_type` (`VARCHAR(64)`), `entity_id` (`BIGINT UNSIGNED NULL`), `changes_summary` (`JSON NULL`), `created_at` (`TIMESTAMP`).

---

## 4. Strategi Pengujian, Backup, dan Pemulihan Database (PRD 02 §6 & §11)

1. **Pemisahan database pengujian:** `backend/phpunit.xml` mengarahkan seluruh pengujian otomatis ke database **`roadpriority_test`**, sehingga `RefreshDatabase` tidak pernah menyentuh database development `roadpriority` maupun database lain di XAMPP.
2. **Backup database development (`roadpriority`):**
   ```powershell
   C:\xampp\mysql\bin\mysqldump.exe -h 127.0.0.1 -u root --single-transaction --routines roadpriority > backup_roadpriority.sql
   ```
3. **Restore database development:**
   ```powershell
   C:\xampp\mysql\bin\mysql.exe -h 127.0.0.1 -u root roadpriority < backup_roadpriority.sql
   ```

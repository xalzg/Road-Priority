# Kamus Data (`DATA_DICTIONARY.md`)

**Tanggal pembaruan:** 29 Oktober 2026  
**Tahap:** PRD 02 — Database Design & API Contract  
**Status:** Selaras 100% dengan berkas migration di `backend/database/migrations/` dan model Eloquent di `backend/app/Models/`.

---

## 1. Akun & Wilayah Administratif

### 1.1 `users`

| Field | Tipe Data | Satuan | Rentang / Nilai Valid | Sumber | Nullable | Aturan Kualitas & Integritas |
|---|---|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | - | $\ge 1$ | Sistem | Tidak | Primary key. |
| `name` | `VARCHAR(255)` | - | Teks tidak kosong | Pengguna / Admin | Tidak | Nama tampilan pengguna. |
| `email` | `VARCHAR(255)` | - | Format email valid (unik) | Pengguna / Admin | Tidak | Unique index `users_email_unique`. |
| `email_verified_at` | `TIMESTAMP` | UTC | Waktu valid | Sistem | Ya | Waktu verifikasi email. |
| `password` | `VARCHAR(255)` | - | Hash bcrypt/argon2 | Sistem | Tidak | Tidak pernah disimpan dalam teks biasa; disembunyikan pada serialisasi (`$hidden`). |
| `role` | `VARCHAR(32)` | Enum | `reporter`, `officer`, `expert`, `admin` | Admin / Sistem | Tidak | Default `'reporter'`; dilindungi dari *mass assignment* (`User::$fillable`). |
| `is_active` | `BOOLEAN` | - | `true` / `false` | Admin | Tidak | Default `true`; dilindungi dari *mass assignment*. |
| `created_at`, `updated_at` | `TIMESTAMP` | UTC | - | Sistem | Ya | Timestamp standar Laravel. |

### 1.2 `administrative_areas`

| Field | Tipe Data | Satuan | Rentang / Nilai Valid | Sumber | Nullable | Aturan Kualitas & Integritas |
|---|---|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | - | $\ge 1$ | Sistem | Tidak | Primary key. |
| `name` | `VARCHAR(191)` | - | Teks tidak kosong | DS-01 (BIG) | Tidak | Nama wilayah (mis. `Kecamatan Serpong`). |
| `administrative_level` | `VARCHAR(32)` | Enum | `provinsi`, `kota`, `kabupaten`, `kecamatan`, `kelurahan`, `kawasan_studi` | DS-01 | Tidak | Tingkat wilayah administratif. |
| `parent_id` | `BIGINT UNSIGNED` | - | FK ke `administrative_areas.id` | DS-01 | Ya | `ON DELETE SET NULL`; hierarki opsional. |
| `external_code` | `VARCHAR(64)` | - | Kode wilayah Kemendagri/BIG (unik) | DS-01 | Ya | Unique index `uq_administrative_areas_external_code`. |
| `boundary_geojson` | `JSON` | Derajat (WGS 84) | Objek GeoJSON `Polygon` / `MultiPolygon` (`[lng, lat]`) | DS-01 | Ya | Digunakan untuk validasi titik laporan di dalam wilayah studi. |
| `is_study_area` | `BOOLEAN` | - | `true` / `false` | Admin | Tidak | Menandai wilayah studi aktif. |

---

## 2. Jaringan Jalan & Pelaporan Kerusakan

### 2.1 `road_segments` (Alternatif Keputusan)

| Field | Tipe Data | Satuan | Rentang / Nilai Valid | Sumber | Nullable | Aturan Kualitas & Integritas |
|---|---|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | - | $\ge 1$ | Sistem | Tidak | Primary key. |
| `administrative_area_id` | `BIGINT UNSIGNED` | - | FK ke `administrative_areas.id` | GIS / Admin | Ya | `ON DELETE SET NULL`. |
| `name` | `VARCHAR(191)` | - | Nama ruas jalan | DS-02 / DS-04 | Ya | Contoh: `Jl. Pahlawan Seribu Segmen 01`. |
| `road_reference_code` | `VARCHAR(64)` | - | Kode unik & stabil (`^[A-Z0-9_-]+$`) | GIS / Admin | Tidak | Unique index `uq_road_segments_reference_code`; dipakai sebagai tie-breaker ke-2 TOPSIS. |
| `geometry_geojson` | `JSON` | Derajat (WGS 84) | GeoJSON `LineString` dengan urutan koordinat **`[longitude, latitude]`** | DS-02 (OSM/BIG) | Ya | Siap dirender Leaflet dan dihitung jarak titik-ke-garisnya di PHP. |
| `centroid_latitude` | `DECIMAL(10,7)` | Derajat lintang | $[-90, 90]$ | Hitung saat impor | Ya | Indeks `idx_road_segments_centroid` untuk filter `bbox`. |
| `centroid_longitude` | `DECIMAL(10,7)` | Derajat bujur | $[-180, 180]$ | Hitung saat impor | Ya | Indeks `idx_road_segments_centroid` untuk filter `bbox`. |
| `length_meters` | `DECIMAL(10,2)` | Meter | $> 0$ | Hitung geodesik | Ya | Panjang segmen jalan dalam meter. |
| `road_function` | `VARCHAR(32)` | Enum | `arteri`, `kolektor`, `lokal`, `lingkungan` | DS-02 / DS-04 | Ya | Dipetakan ke skor ordinal K3 (4, 3, 2, 1). |
| `road_authority` | `VARCHAR(32)` | Enum | `nasional`, `provinsi`, `kota`, `kabupaten`, `kawasan_pengembang`, `belum_terverifikasi` | DS-04 / Survei | Ya | Menjawab **D-04** & risiko **R-07**. |
| `nearest_facility_meters` | `DECIMAL(10,2)` | Meter | $\ge 0$ | Jarak ke POI DS-02 | Ya | Nilai dasar untuk kriteria **K5** (Cost). |
| `source_name` | `VARCHAR(191)` | - | Nama sumber geometri | Impor | Ya | Contoh: `OpenStreetMap`. |
| `source_reference` | `VARCHAR(255)` | - | ID/URL referensi sumber | Impor | Ya | Contoh: `way/10001 (ODbL)`. |
| `data_origin` | `VARCHAR(32)` | Enum | `citizen_report`, `field_survey`, `public_dataset`, `demo` | Sistem | Tidak | Default `'public_dataset'`. |
| `is_demo` | `BOOLEAN` | - | `true` / `false` | Sistem | Tidak | Default `false`; baris `true` ditolak pada run resmi. |
| `is_active` | `BOOLEAN` | - | `true` / `false` | Admin | Tidak | Default `true`. |
| `last_verified_at` | `TIMESTAMP` | UTC | - | Petugas / Admin | Ya | Waktu verifikasi atribut/geometri terakhir. |

### 2.2 `damage_reports`

| Field | Tipe Data | Satuan | Rentang / Nilai Valid | Sumber | Nullable | Aturan Kualitas & Integritas |
|---|---|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | - | $\ge 1$ | Sistem | Tidak | Primary key internal. |
| `report_code` | `VARCHAR(36)` | - | Kode unik publik / ULID | Sistem | Tidak | Unique index `uq_damage_reports_report_code`. |
| `reporter_id` | `BIGINT UNSIGNED` | - | FK ke `users.id` | Autentikasi | Ya | `ON DELETE SET NULL`; tidak pernah diekspos di endpoint publik. |
| `road_segment_id` | `BIGINT UNSIGNED` | - | FK ke `road_segments.id` | Map-matching + Petugas | Ya | `ON DELETE SET NULL`. |
| `segment_match_distance_m` | `DECIMAL(8,2)` | Meter | $\ge 0$ | Service layer PHP | Ya | Jarak titik laporan ke garis ruas jalan. |
| `damage_type` | `VARCHAR(64)` | Kode jenis | `pothole`, `alligator_cracking`, `rutting`, `depression`, `surface_ravelling`, dll. | Pelapor | Tidak | Jenis kerusakan jalan. |
| `description` | `TEXT` | Karakter | $10 \dots 1000$ karakter | Pelapor | Tidak | Deskripsi laporan. |
| `latitude` | `DECIMAL(10,7)` | Derajat lintang | $[-90, 90]$ (dan dalam wilayah studi) | GPS / Peta | Tidak | Disimpan dalam WGS 84. |
| `longitude` | `DECIMAL(10,7)` | Derajat bujur | $[-180, 180]$ (dan dalam wilayah studi) | GPS / Peta | Tidak | Disimpan dalam WGS 84. |
| `gps_accuracy_m` | `DECIMAL(8,2)` | Meter | $> 0$ | Perangkat pelapor | Ya | Akurasi GPS perangkat. |
| `reported_at` | `TIMESTAMP` | UTC | $\le \text{now()}$ | Pelapor / Sistem | Tidak | Waktu pelaporan/pengamatan. |
| `status` | `VARCHAR(32)` | Enum | `submitted`, `under_review`, `needs_info`, `verified`, `rejected`, `resolved` | State machine §8 | Tidak | Default `'submitted'`. |
| `verification_notes` | `TEXT` | - | Catatan verifikasi/penolakan | Petugas | Ya | Wajib diisi saat `rejected` atau `needs_info`. |
| `verified_by` | `BIGINT UNSIGNED` | - | FK ke `users.id` | Petugas | Ya | `ON DELETE SET NULL`. |
| `verified_at` | `TIMESTAMP` | UTC | - | Sistem | Ya | Waktu transisi ke `verified`. |
| `duplicate_of_id` | `BIGINT UNSIGNED` | - | FK ke `damage_reports.id` | Petugas | Ya | Laporan duplikat tertaut tidak dihitung ganda pada K6. |
| `data_origin` | `VARCHAR(32)` | Enum | `citizen_report`, `field_survey`, `public_dataset`, `demo` | Sistem | Tidak | Default `'citizen_report'`. |
| `is_demo` | `BOOLEAN` | - | `true` / `false` | Sistem | Tidak | Default `false`. |

### 2.3 `report_photos` & `report_status_histories`

| Tabel | Field Utama | Tipe & Aturan Kualitas |
|---|---|---|
| `report_photos` | `damage_report_id` (`FK CASCADE`), `storage_disk` (`VARCHAR(32)`), `file_path` (`VARCHAR(255)`), `original_filename` (`VARCHAR(255) NULL`), `mime_type` (`VARCHAR(64)`), `file_size_bytes` (`BIGINT UNSIGNED`), `width`, `height`, `sha256` (`CHAR(64) NULL`), `is_public` (`BOOLEAN DEFAULT false`), `captured_at` (`TIMESTAMP NULL`) | Menyimpan lokasi file di luar webroot (bukan blob di DB); `is_public` default `false` demi privasi (NFR-04, NFR-05). |
| `report_status_histories` | `damage_report_id` (**`FK RESTRICT`**), `previous_status` (`VARCHAR(32) NULL`), `new_status` (`VARCHAR(32)`), `changed_by` (`FK SET NULL`), `notes` (`TEXT NULL`), `changed_at` (`TIMESTAMP`) | **Append-only**; mencegah penghapusan laporan yang telah memiliki histori. |

---

## 3. Penilaian Kondisi & Keputusan AHP-TOPSIS

### 3.1 `damage_assessments`

| Field | Tipe Data | Satuan | Rentang / Nilai Valid | Sumber | Nullable | Aturan Kualitas & Integritas |
|---|---|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | - | $\ge 1$ | Sistem | Tidak | Primary key. |
| `road_segment_id` | `BIGINT UNSIGNED` | - | FK ke `road_segments.id` (**`RESTRICT`**) | Petugas | Ya* | *Minimal satu dari `road_segment_id` atau `damage_report_id` wajib diisi (divalidasi model). |
| `damage_report_id` | `BIGINT UNSIGNED` | - | FK ke `damage_reports.id` (`SET NULL`) | Petugas | Ya* | Opsional bila penilaian berasal dari survei ruas langsung. |
| `assessed_by` | `BIGINT UNSIGNED` | - | FK ke `users.id` (`SET NULL`) | Autentikasi | Ya | Petugas penilai. |
| `assessment_date` | `TIMESTAMP` | UTC | $\le \text{now()}$ | Petugas | Tidak | Tanggal pemeriksaan kondisi. |
| `protocol_version` | `VARCHAR(32)` | - | Default `'SDI-ADAPTED-1.0'` | Sistem | Tidak | Mengacu pada `FIELD_PROTOCOL.md`. |
| `severity_level` | `DECIMAL(8,4)` | Skala / Indeks | $1 \dots 5$ (atau nilai SDI) | Petugas | Tidak | Nilai untuk **K1** (Tingkat kerusakan). |
| `safety_risk_score` | `DECIMAL(8,4)` | Skala 1–5 | $1 \dots 5$ | Petugas | Ya | Nilai untuk **K2** (Risiko keselamatan). |
| `road_function_score` | `DECIMAL(8,4)` | Skala 1–4 | $1 \dots 4$ | Petugas / Segmen | Ya | Nilai untuk **K3** (Fungsi jalan). |
| `impact_score` | `DECIMAL(8,4)` | Skala 1–5 | $1 \dots 5$ | Petugas | Ya | Nilai untuk **K4** (Intensitas pengguna jalan / dampak). |
| `measurements` | `JSON` | - | Parameter mentah SDI (`crack_area_pct`, `crack_width_mm`, `pothole_count_per_100m`, `rutting_depth_cm`) | Petugas | Ya | Untuk ketertelusuran audit. |
| `status` | `VARCHAR(16)` | Enum | `draft`, `final` | Petugas | Tidak | Default `'draft'`; status `'final'` bersifat immutable. |
| `assessment_source` | `VARCHAR(32)` | Enum | `field_survey`, `report_verification`, `public_dataset`, `demo` | Sistem | Tidak | Default `'field_survey'`. |
| `is_demo` | `BOOLEAN` | - | `true` / `false` | Sistem | Tidak | Default `false`. |

### 3.2 `decision_criteria`, `criterion_weight_versions`, `criterion_weight_items`, `ahp_pairwise_comparisons`

| Tabel | Kolom & Tipe Utama | Aturan Integritas |
|---|---|---|
| `decision_criteria` | `code` (`VARCHAR(32) UNIQUE`), `name`, `description`, `data_type`, `unit`, `criterion_type` (`benefit`/`cost`), `minimum_value` (`DECIMAL(14,4) NULL`), `maximum_value` (`DECIMAL(14,4) NULL`), `aggregation_rule`, `is_required`, `is_active` | Kriteria kandidat K1–K7 diisi melalui `CandidateCriteriaSeeder`. |
| `criterion_weight_versions` | `version_name`, `status` (`draft`/`computed`/`approved`/`archived`), `criteria_count`, `lambda_max` (`DECIMAL(18,12)`), `consistency_index` (`DECIMAL(18,12)`), `consistency_ratio` (`DECIMAL(18,12)`), `is_consistent`, `aggregation_method`, `created_by`, `approved_by`, `approved_at` | Hanya versi `approved` dengan `is_consistent = true` ($CR \le 0{,}10$) yang dapat digunakan pada decision run resmi. |
| `criterion_weight_items` | `criterion_weight_version_id` (`FK CASCADE`), `decision_criterion_id` (**`FK RESTRICT`**), `weight_value` (`DECIMAL(18,12)`) | Unique `(criterion_weight_version_id, decision_criterion_id)`; jumlah bobot $= 1$ (toleransi $10^{-9}$). |
| `ahp_pairwise_comparisons` | `criterion_weight_version_id` (`FK CASCADE`), `expert_id` (`FK SET NULL`), `criterion_a_id` (**`FK RESTRICT`**), `criterion_b_id` (**`FK RESTRICT`**), `comparison_value` (`DECIMAL(18,12)`) | Unique `(criterion_weight_version_id, expert_id, criterion_a_id, criterion_b_id)`. |

### 3.3 `decision_runs`, `decision_run_items`, & `audit_logs`

| Tabel | Kolom & Tipe Utama | Aturan Integritas |
|---|---|---|
| `decision_runs` | `run_code` (`VARCHAR(36) UNIQUE`), `name`, `criterion_weight_version_id` (**`FK RESTRICT`**), `algorithm_name`, `algorithm_version`, `status`, `is_simulation`, `input_hash` (`CHAR(64)`), `input_snapshot_reference` (`JSON`), `executed_by` (`FK SET NULL`), `executed_at` | Menyimpan snapshot beku konfigurasi dan hash SHA-256 kanonik (`InputHasher`). |
| `decision_run_items` | `decision_run_id` (`FK CASCADE`), `road_segment_id` (**`FK RESTRICT`**), `is_included`, `exclusion_reason`, `rank_position` (`INT UNSIGNED NULL`), `preference_score` (`DECIMAL(18,12) NULL`), `d_plus` (`DECIMAL(18,12) NULL`), `d_minus` (`DECIMAL(18,12) NULL`), `largest_driving_criterion`, `input_values_snapshot` (`JSON`), `intermediate_values_snapshot` (`JSON NULL`) | Unique `(decision_run_id, road_segment_id)` dan `(decision_run_id, rank_position)`. |
| `audit_logs` | `actor_id` (`FK SET NULL`), `action` (`VARCHAR(64)`), `entity_type` (`VARCHAR(64)`), `entity_id` (`BIGINT UNSIGNED NULL`), `changes_summary` (`JSON NULL`), `created_at` (`TIMESTAMP`) | **Append-only**; dilarang menyimpan password, token, atau rahasia. |

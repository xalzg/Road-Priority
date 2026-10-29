# Entity-Relationship Diagram (`ERD.md`)

**Tahap:** PRD 02 — Database Design & API Contract  
**Database:** MySQL / MariaDB 10.4.32 (`roadpriority` untuk development, `roadpriority_test` untuk pengujian otomatis)

---

## 1. Diagram Relasi Entitas (Mermaid `erDiagram`)

```mermaid
erDiagram
    USERS ||--o{ DAMAGE_REPORTS : "melaporkan (reporter_id)"
    USERS ||--o{ DAMAGE_REPORTS : "memverifikasi (verified_by)"
    USERS ||--o{ REPORT_STATUS_HISTORIES : "mengubah status (changed_by)"
    USERS ||--o{ DAMAGE_ASSESSMENTS : "menilai kondisi (assessed_by)"
    USERS ||--o{ CRITERION_WEIGHT_VERSIONS : "membuat (created_by)"
    USERS ||--o{ CRITERION_WEIGHT_VERSIONS : "menyetujui (approved_by)"
    USERS ||--o{ AHP_PAIRWISE_COMPARISONS : "mengisi AHP (expert_id)"
    USERS ||--o{ DECISION_RUNS : "menjalankan (executed_by)"
    USERS ||--o{ AUDIT_LOGS : "mencatat aksi (actor_id)"

    ADMINISTRATIVE_AREAS ||--o{ ADMINISTRATIVE_AREAS : "induk wilayah (parent_id)"
    ADMINISTRATIVE_AREAS ||--o{ ROAD_SEGMENTS : "mencakup (administrative_area_id)"

    ROAD_SEGMENTS ||--o{ DAMAGE_REPORTS : "lokasi ruas (road_segment_id)"
    ROAD_SEGMENTS ||--o{ DAMAGE_ASSESSMENTS : "dinilai pada (road_segment_id)"
    ROAD_SEGMENTS ||--o{ DECISION_RUN_ITEMS : "alternatif keputusan (road_segment_id)"

    DAMAGE_REPORTS ||--o{ DAMAGE_REPORTS : "duplikat dari (duplicate_of_id)"
    DAMAGE_REPORTS ||--o{ REPORT_PHOTOS : "memiliki foto (damage_report_id)"
    DAMAGE_REPORTS ||--o{ REPORT_STATUS_HISTORIES : "memiliki riwayat (damage_report_id)"
    DAMAGE_REPORTS ||--o{ DAMAGE_ASSESSMENTS : "ditindaklanjuti (damage_report_id)"

    DECISION_CRITERIA ||--o{ CRITERION_WEIGHT_ITEMS : "memiliki bobot (decision_criterion_id)"
    DECISION_CRITERIA ||--o{ AHP_PAIRWISE_COMPARISONS : "kriteria A (criterion_a_id)"
    DECISION_CRITERIA ||--o{ AHP_PAIRWISE_COMPARISONS : "kriteria B (criterion_b_id)"

    CRITERION_WEIGHT_VERSIONS ||--o{ CRITERION_WEIGHT_ITEMS : "rincian bobot (criterion_weight_version_id)"
    CRITERION_WEIGHT_VERSIONS ||--o{ AHP_PAIRWISE_COMPARISONS : "matriks AHP (criterion_weight_version_id)"
    CRITERION_WEIGHT_VERSIONS ||--o{ DECISION_RUNS : "dipakai oleh (criterion_weight_version_id)"

    DECISION_RUNS ||--o{ DECISION_RUN_ITEMS : "menghasilkan peringkat (decision_run_id)"

    USERS {
        bigint id PK
        string name
        string email UK
        string password
        string role
        boolean is_active
    }

    ADMINISTRATIVE_AREAS {
        bigint id PK
        string name
        string administrative_level
        bigint parent_id FK
        string external_code UK
        json boundary_geojson
        boolean is_study_area
    }

    ROAD_SEGMENTS {
        bigint id PK
        bigint administrative_area_id FK
        string name
        string road_reference_code UK
        json geometry_geojson
        decimal centroid_latitude
        decimal centroid_longitude
        decimal length_meters
        string road_function
        string road_authority
        decimal nearest_facility_meters
        string source_name
        string source_reference
        string data_origin
        boolean is_demo
        boolean is_active
    }

    DAMAGE_REPORTS {
        bigint id PK
        string report_code UK
        bigint reporter_id FK
        bigint road_segment_id FK
        decimal segment_match_distance_m
        string damage_type
        text description
        decimal latitude
        decimal longitude
        decimal gps_accuracy_m
        timestamp reported_at
        string status
        bigint verified_by FK
        timestamp verified_at
        bigint duplicate_of_id FK
        string data_origin
        boolean is_demo
    }

    REPORT_PHOTOS {
        bigint id PK
        bigint damage_report_id FK
        string storage_disk
        string file_path
        string mime_type
        bigint file_size_bytes
        boolean is_public
    }

    REPORT_STATUS_HISTORIES {
        bigint id PK
        bigint damage_report_id FK
        string previous_status
        string new_status
        bigint changed_by FK
        text notes
        timestamp changed_at
    }

    DAMAGE_ASSESSMENTS {
        bigint id PK
        bigint road_segment_id FK
        bigint damage_report_id FK
        bigint assessed_by FK
        timestamp assessment_date
        string protocol_version
        decimal severity_level
        decimal safety_risk_score
        decimal road_function_score
        decimal impact_score
        json measurements
        string status
        string assessment_source
        boolean is_demo
    }

    DECISION_CRITERIA {
        bigint id PK
        string code UK
        string name
        string data_type
        string unit
        string criterion_type
        decimal minimum_value
        decimal maximum_value
        boolean is_required
        boolean is_active
    }

    CRITERION_WEIGHT_VERSIONS {
        bigint id PK
        string version_name
        string status
        smallint criteria_count
        decimal lambda_max
        decimal consistency_index
        decimal consistency_ratio
        boolean is_consistent
        bigint created_by FK
        bigint approved_by FK
    }

    CRITERION_WEIGHT_ITEMS {
        bigint id PK
        bigint criterion_weight_version_id FK
        bigint decision_criterion_id FK
        decimal weight_value
    }

    AHP_PAIRWISE_COMPARISONS {
        bigint id PK
        bigint criterion_weight_version_id FK
        bigint expert_id FK
        bigint criterion_a_id FK
        bigint criterion_b_id FK
        decimal comparison_value
    }

    DECISION_RUNS {
        bigint id PK
        string run_code UK
        bigint criterion_weight_version_id FK
        string algorithm_name
        string algorithm_version
        string status
        boolean is_simulation
        char input_hash
        json input_snapshot_reference
        bigint executed_by FK
        timestamp executed_at
    }

    DECISION_RUN_ITEMS {
        bigint id PK
        bigint decision_run_id FK
        bigint road_segment_id FK
        boolean is_included
        string exclusion_reason
        int rank_position
        decimal preference_score
        decimal d_plus
        decimal d_minus
        string largest_driving_criterion
        json input_values_snapshot
        json intermediate_values_snapshot
    }

    AUDIT_LOGS {
        bigint id PK
        bigint actor_id FK
        string action
        string entity_type
        bigint entity_id
        json changes_summary
        timestamp created_at
    }
```

---

## 2. Ringkasan Kardinalitas & Kebijakan Penghapusan (`ON DELETE`)

| Relasi Induk $\rightarrow$ Anak | Foreign Key | Nullable | `ON DELETE` | Alasan Desain & Audit |
|---|---|---|---|---|
| `administrative_areas` $\rightarrow$ `administrative_areas` | `parent_id` | Ya | `SET NULL` | Hierarki administratif opsional (PRD 02 §4.3). |
| `administrative_areas` $\rightarrow$ `road_segments` | `administrative_area_id` | Ya | `SET NULL` | Ruas jalan tetap tersimpan meskipun batas wilayah diperbarui. |
| `road_segments` $\rightarrow$ `damage_reports` | `road_segment_id` | Ya | `SET NULL` | Laporan awal dapat masuk sebelum *map-matching* dikonfirmasi petugas. |
| `damage_reports` $\rightarrow$ `report_photos` | `damage_report_id` | Tidak | `CASCADE` | Metadata foto melekat langsung pada laporan induknya. |
| `damage_reports` $\rightarrow$ `report_status_histories` | `damage_report_id` | Tidak | **`RESTRICT`** | Mencegah penghapusan laporan yang sudah memiliki jejak riwayat status (PRD 02 §5 & §11). |
| `road_segments` $\rightarrow$ `damage_assessments` | `road_segment_id` | Ya* | **`RESTRICT`** | Ruas yang sudah memiliki penilaian kondisi lapangan tidak boleh dihapus. (*Minimal satu dari `road_segment_id` atau `damage_report_id` wajib terisi). |
| `decision_criteria` $\rightarrow$ `criterion_weight_items` | `decision_criterion_id` | Tidak | **`RESTRICT`** | Kriteria yang pernah dipakai pada versi bobot tidak boleh dihapus. |
| `criterion_weight_versions` $\rightarrow$ `decision_runs` | `criterion_weight_version_id` | Tidak | **`RESTRICT`** | Versi bobot yang sudah dipakai oleh eksekusi keputusan tidak boleh dihapus. |
| `decision_runs` $\rightarrow$ `decision_run_items` | `decision_run_id` | Tidak | `CASCADE` | Item hasil merupakan bagian dari satu kesatuan eksekusi `decision_runs`. |
| `road_segments` $\rightarrow$ `decision_run_items` | `road_segment_id` | Tidak | **`RESTRICT`** | Ruas jalan yang pernah menjadi alternatif dalam histori keputusan tidak boleh dihapus. |
| `users` $\rightarrow$ seluruh tabel transaksi/histori | `reporter_id`, `verified_by`, `changed_by`, `assessed_by`, `created_by`, `approved_by`, `expert_id`, `executed_by`, `actor_id` | Ya | `SET NULL` | Jika akun pengguna dinonaktifkan/dihapus, seluruh histori laporan, penilaian, bobot, keputusan, dan audit log tetap utuh. |


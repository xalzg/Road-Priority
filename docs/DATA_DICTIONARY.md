# Kamus Data Awal (`DATA_DICTIONARY.md`)

**Tanggal pembaruan:** 29 Oktober 2026  
**Ruang lingkup:** Master PRD v2.0 — PRD 01 Bagian E & PRD 02 §2.2  
**Catatan:** Kamus data ini menjadi acuan pembuatan migration pada PRD 02.

---

## 1. Entitas Inti & Spasial

### 1.1 `road_segments` (Alternatif Keputusan / Ruas Jalan)

| Field | Tipe Data | Satuan | Rentang / Nilai Valid | Sumber | Nullable | Aturan Kualitas & Integritas |
|---|---|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | - | $\ge 1$ | Sistem | Tidak | Primary key internal. |
| `code` | `VARCHAR(64)` | - | Pola `^[A-Z0-9_-]+$` (unik) | Impor GIS / Admin | Tidak | **Stabil antar-impor**; dipakai sebagai tie-breaker ke-2 pada TOPSIS. |
| `name` | `VARCHAR(191)` | - | Teks tidak kosong | DS-02 / DS-04 | Tidak | Nama ruas jalan (mis. `Jl. Pahlawan Seribu Segmen 01`). |
| `geometry` | `LINESTRING` (SRID 4326) | Derajat (WGS 84) | Urutan koordinat `(longitude, latitude)` dalam wilayah studi | DS-02 (OSM / BIG) | Tidak | Wajib memiliki `SPATIAL INDEX`; minimal 2 titik berbeda. |
| `length_m` | `DECIMAL(10,2)` | Meter | $> 0$ | Hitung geodesik saat impor | Tidak | Panjang segmen jalan dalam meter. |
| `road_function` | `VARCHAR(32)` | Enum | `arteri`, `kolektor`, `lokal`, `lingkungan` | DS-02 / DS-04 | Tidak | Dipetakan ke skor ordinal K3 (4, 3, 2, 1) [USULAN D-06]. |
| `road_authority` | `VARCHAR(32)` | Enum | `nasional`, `provinsi`, `kota`, `kabupaten`, `kawasan_pengembang`, `belum_terverifikasi` | DS-04 / Survei | Tidak | Menjawab **D-04** & risiko **R-07** (kewenangan jalan BSD). |
| `nearest_facility_m` | `DECIMAL(10,2)` | Meter | $\ge 0$ | Hitung jarak ke POI DS-02 | Ya | Nilai dasar untuk kriteria **K5** (Cost). |
| `data_source_id` | `BIGINT UNSIGNED` | - | FK ke `data_sources.id` | Sistem | Ya | Menjamin ketertelusuran sumber geometri. |
| `data_origin` | `VARCHAR(32)` | Enum | `citizen_report`, `field_survey`, `public_dataset`, `demo` | Sistem | Tidak | Data `demo` dilarang masuk decision run resmi. |
| `is_active` | `BOOLEAN` | - | `true` / `false` | Admin | Tidak | Hanya ruas aktif dalam wilayah studi yang dievaluasi. |

### 1.2 `damage_reports` (Laporan Kerusakan Jalan)

| Field | Tipe Data | Satuan | Rentang / Nilai Valid | Sumber | Nullable | Aturan Kualitas & Integritas |
|---|---|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | - | $\ge 1$ | Sistem | Tidak | Primary key internal. |
| `public_id` | `CHAR(26)` | - | ULID (unik) | Sistem | Tidak | Dipakai pada URL/API publik untuk mencegah IDOR. |
| `reporter_id` | `BIGINT UNSIGNED` | - | FK ke `users.id` | Autentikasi | Tidak | Tidak pernah ditampilkan pada endpoint publik (NFR-05). |
| `damage_type_id` | `BIGINT UNSIGNED` | - | FK ke `damage_types.id` | Pelapor | Tidak | Jenis kerusakan (lubang, retak buaya, amblas, bergelombang, dll.). |
| `description` | `TEXT` | Karakter | $10 \dots 1000$ karakter | Pelapor | Tidak | Deskripsi kondisi kerusakan. |
| `latitude` | `DECIMAL(10,7)` | Derajat lintang | $-6{,}40 \le \text{lat} \le -6{,}20$ (dan di dalam poligon studi) | GPS / Peta | Tidak | Disimpan dalam WGS 84. |
| `longitude` | `DECIMAL(10,7)` | Derajat bujur | $106{,}55 \le \text{lng} \le 106{,}80$ (dan di dalam poligon studi) | GPS / Peta | Tidak | Disimpan dalam WGS 84. |
| `location` | `POINT` (SRID 4326) | Derajat | `Point(longitude, latitude)` | Sistem | Tidak | Wajib memiliki `SPATIAL INDEX`. |
| `gps_accuracy_m` | `DECIMAL(8,2)` | Meter | $> 0$ | Perangkat pelapor | Ya | Jika $> 50\text{ m}$, beri penanda akurasi rendah (FR-REP-02). |
| `road_segment_id` | `BIGINT UNSIGNED` | - | FK ke `road_segments.id` | Map-matching + Petugas | Ya | Kandidat awal diusulkan dalam radius $30\text{ m}$; final setelah dikonfirmasi petugas. |
| `segment_match_distance_m` | `DECIMAL(8,2)` | Meter | $\ge 0$ | Service layer PHP | Ya | Jarak titik laporan ke garis ruas jalan (dihitung di PHP; lihat `PROJECT_AUDIT.md`). |
| `status` | `VARCHAR(24)` | Enum | `submitted`, `under_review`, `needs_info`, `verified`, `rejected`, `resolved` | State machine §8 | Tidak | Hanya laporan `verified` yang dihitung pada kriteria K6 & K7. |
| `observed_at` | `DATETIME` | UTC | $\le$ waktu kirim (`now()`) | Pelapor | Tidak | Tidak boleh di masa depan (FR-REP-01). |
| `duplicate_of_id` | `BIGINT UNSIGNED` | - | FK ke `damage_reports.id` | Petugas | Ya | Laporan duplikat tertaut tetap tersimpan tetapi **tidak dihitung ganda** pada K6. |
| `data_origin` | `VARCHAR(32)` | Enum | `citizen_report`, `field_survey`, `public_dataset`, `demo` | Sistem | Tidak | Wajib diisi. |
| `is_demo` | `BOOLEAN` | - | `true` / `false` | Sistem | Tidak | Default `false`; seeder demo wajib mengisi `true`. |

---

## 2. Entitas Penilaian Kondisi & Mesin Keputusan AHP-TOPSIS

### 2.1 `damage_assessments` (Penilaian Kondisi Lapangan oleh Petugas)

| Field | Tipe Data | Satuan | Rentang / Nilai Valid | Sumber | Nullable | Aturan Kualitas & Integritas |
|---|---|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | - | $\ge 1$ | Sistem | Tidak | Primary key. |
| `road_segment_id` | `BIGINT UNSIGNED` | - | FK ke `road_segments.id` | Petugas | Tidak | Ruas yang dinilai. |
| `assessor_id` | `BIGINT UNSIGNED` | - | FK ke `users.id` | Autentikasi | Tidak | Petugas berwenang / peneliti lapangan. |
| `assessed_at` | `DATETIME` | UTC | $\le \text{now()}$ | Petugas | Tidak | Waktu pengamatan lapangan. |
| `protocol_version` | `VARCHAR(32)` | - | Mis. `SDI-ADAPTED-1.0` | Sistem | Tidak | Mengacu pada `FIELD_PROTOCOL.md` (D-08). |
| `sdi_score` | `DECIMAL(8,2)` | Indeks SDI | $\ge 0$ ($<50$ Baik, $50–99$ Sedang, $100–150$ Rusak Ringan, $>150$ Rusak Berat) | Hitung dari 4 parameter SDI | Ya | Opsional bila memakai skala ordinal langsung; wajib bila protokol SDI penuh dipakai. |
| `severity_score` | `TINYINT UNSIGNED` | Skala ordinal | $1 \dots 5$ | Petugas | Tidak | Nilai untuk **K1** (Tingkat kerusakan, Benefit). |
| `safety_risk_score` | `TINYINT UNSIGNED` | Skala ordinal | $1 \dots 5$ | Petugas | Tidak | Nilai untuk **K2** (Risiko keselamatan, Benefit). |
| `traffic_intensity_score` | `TINYINT UNSIGNED` | Skala ordinal | $1 \dots 5$ | Petugas / Data LHR | Ya | Nilai untuk **K4** bila memakai rubrik kelas lalu lintas lapangan. |
| `measurements` | `JSON` | - | Objek JSON parameter lapangan (luas retak %, lebar retak mm, jml lubang, kedalaman alur cm) | Petugas | Tidak | Menyimpan rincian mentah agar skor K1/SDI dapat diaudit. |
| `status` | `VARCHAR(16)` | Enum | `draft`, `final` | Petugas | Tidak | Rekaman `final` bersifat **immutable** (tidak dapat diubah). |
| `data_origin` | `VARCHAR(32)` | Enum | `field_survey`, `demo` | Sistem | Tidak | Wajib diisi. |

### 2.2 Nilai Kriteria & Hasil Perhitungan (`decision_runs`, `decision_run_items`)

| Field | Tipe Data | Satuan | Rentang / Nilai Valid | Sumber | Nullable | Aturan Kualitas & Integritas |
|---|---|---|---|---|---|---|
| `lambda_max`, `ci`, `cr` | `DECIMAL(18,12)` | Rasio | $CR \ge 0$; konsisten bila $CR \le 0{,}10$ | `AhpService` | Tidak | Disimpan pada `criterion_weight_versions`. |
| `weight` | `DECIMAL(18,12)` | Proporsi | $0 \le w_j \le 1$, $|\sum w_j - 1| \le 10^{-9}$ | `AhpService` | Tidak | Disimpan pada `criterion_weights` & snapshot. |
| `d_plus`, `d_minus` | `DECIMAL(18,12)` | Jarak Euclid | $\ge 0$ | `TopsisService` | Ya (jika `included = false`) | Disimpan dengan presisi 12 desimal; dibulatkan hanya saat tampilan. |
| `closeness` ($C_i$) | `DECIMAL(18,12)` | Indeks preferensi | $0 \le C_i \le 1$ | `TopsisService` | Ya (jika `included = false`) | Nilai utama pengurutan prioritas. |
| `rank` | `INT UNSIGNED` | Peringkat | $1 \dots m$ | `TopsisService` | Ya (jika `included = false`) | Peringkat 1 = prioritas penanganan tertinggi. |
| `input_hash` | `CHAR(64)` | Heksadesimal | SHA-256 (`^[a-f0-9]{64}$`) | `InputHasher` | Tidak | Menjamin verifikasi reproduksibilitas (NFR-12). |

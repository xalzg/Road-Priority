# Kontrak API (`API_CONTRACT.md`)

**Tahap:** PRD 02 & PRD 03 — Database Design, API Contract, Authentication & Access Control  
**Format pertukaran:** JSON (`Content-Type: application/json`, kecuali upload multipart pada `POST /api/reports`)  
**Format waktu:** ISO 8601 (`YYYY-MM-DDTHH:mm:ssZ`, disimpan dalam UTC, ditampilkan Asia/Jakarta di UI)  
**Sistem koordinat:** WGS 84 (EPSG:4326) — field objek memakai `latitude` ($[-90, 90]$) dan `longitude` ($[-180, 180]$); koordinat di dalam objek GeoJSON memakai urutan **`[longitude, latitude]`**.

---

## 1. Standar Amplop Respons & Kode Status HTTP

### 1.1 Respons Sukses
```json
{
  "success": true,
  "message": "Request successful",
  "data": {}
}
```
*(Untuk daftar berpaginasi, `data` berisi `items` dan `meta: { current_page, per_page, total, last_page }`).*

### 1.2 Respons Error Konsisten (`app/Exceptions/Handler.php`)
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "latitude": [
      "The latitude must be between -90 and 90."
    ]
  }
}
```

| Kode HTTP | Makna |
|---|---|
| `200 OK` / `201 Created` | Permintaan berhasil diproses / resource berhasil dibuat |
| `401 Unauthorized` | Belum login, kredensial salah, atau token Sanctum tidak valid |
| `403 Forbidden` | Akun nonaktif atau peran pengguna tidak memiliki izin (Middleware/Policy menolak akses) |
| `404 Not Found` | Resource tidak ditemukan |
| `409 Conflict` | Transisi status laporan melanggar state machine atau percobaan menonaktifkan/menurunkan role Admin aktif terakhir |
| `422 Unprocessable Entity` | Validasi input gagal (`errors` berisi daftar pesan per field) |
| `429 Too Many Requests` | Melebihi batas rate limiter (`throttle:login` atau `throttle:api`) |
| `500 Internal Server Error` | Kesalahan server terstandar (`errors: null`, tanpa membocorkan stack trace, SQL query, atau path file) |

---

## 2. Ringkasan Status Endpoint

| Metode | Path | Akses / Peran | Status | Target Tahap |
|---|---|---|---|---|
| `GET` | `/api/health` | Publik | **`implemented`** | PRD 01 |
| `POST` | `/api/login` | Tamu (`throttle:login`) | **`implemented`** | PRD 03 |
| `POST` | `/api/logout` | Login (`auth:sanctum`, `active.user`) | **`implemented`** | PRD 03 |
| `GET` | `/api/me` | Login (`auth:sanctum`, `active.user`) | **`implemented`** | PRD 03 |
| `GET` | `/api/users` | Admin (`role:admin`) | **`implemented`** | PRD 03 |
| `POST` | `/api/users` | Admin (`role:admin`) | **`implemented`** | PRD 03 |
| `PATCH` | `/api/users/{user}` | Admin (`role:admin`) | **`implemented`** | PRD 03 |
| `GET` | `/api/audit-logs` | Admin (`role:admin`) | **`implemented`** | PRD 03 |
| `GET` | `/api/reports/{reportIdentifier}` | Publik (jika `verified`/`resolved` non-demo) / Pemilik / Petugas / Admin | **`implemented`** | PRD 03 & PRD 04 |
| `PATCH` | `/api/reports/{damageReport}/status` | Petugas, Admin (`role:officer,admin`) | **`implemented`** | PRD 03 & PRD 04 |
| `GET` | `/api/criteria` | Petugas, Pakar, Admin (`role:officer,expert,admin`) | **`implemented`** | PRD 03 |
| `POST` | `/api/criteria` | Admin (`role:admin`) | **`implemented`** | PRD 03 |
| `GET` | `/api/road-segments` | Publik (non-demo) / Petugas & Admin | **`implemented`** | PRD 04 |
| `GET` | `/api/road-segments/{segmentIdentifier}` | Publik (non-demo) / Petugas & Admin | **`implemented`** | PRD 04 |
| `GET` | `/api/reports` | Publik (hanya `verified`/`resolved` non-demo) / Pelapor (`mine=1`) / Petugas & Admin (semua) | **`implemented`** | PRD 04 |
| `POST` | `/api/reports` | Pelapor, Petugas, Admin (`auth:sanctum`, `active.user`, `role:reporter,officer,admin`) | **`implemented`** | PRD 04 |
| `GET` | `/api/reports/{damageReport}/photos/{reportPhoto}` | Publik (jika laporan `verified` & `is_public=true`) / Pemilik / Petugas & Admin | **`implemented`** | PRD 04 |
| `GET` | `/api/criterion-weight-versions` | Petugas, Pakar, Admin | `planned` | PRD 05 |
| `POST` | `/api/decision-runs` | Admin (atau Petugas berizin) | `planned` | PRD 05 |
| `GET` | `/api/decision-runs` | Petugas, Pakar, Admin | `planned` | PRD 05 |
| `GET` | `/api/decision-runs/{id}` | Petugas, Pakar, Admin | `planned` | PRD 05 |

---

## 3. Spesifikasi Detail Endpoint

### 3.1 `GET /api/health` (`implemented`)
- **Tujuan:** Memeriksa ketersediaan layanan API tanpa mengekspos konfigurasi internal.
- **Autentikasi:** Publik.
- **Respons `200 OK`:**
  ```json
  {
    "success": true,
    "message": "RoadPriority API is running"
  }
  ```

---

### 3.2 `GET /api/road-segments` (`planned`)
- **Tujuan:** Mengambil daftar ruas jalan (`road_segments`) beserta metadata non-sensitif untuk peta atau tabel.
- **Autentikasi:** Publik.
- **Parameter Query:**
  - `page` (`int`, default `1`), `per_page` (`int`, default `20`, maks `100`).
  - `administrative_area_id` (`int`, opsional).
  - `road_function` (`string`, opsional: `arteri|kolektor|lokal|lingkungan`).
  - `road_authority` (`string`, opsional: `nasional|provinsi|kota|kabupaten|kawasan_pengembang|belum_terverifikasi`).
  - `bbox` (`string`, opsional: `minLng,minLat,maxLng,maxLat` — memfilter `centroid_longitude` dan `centroid_latitude`).
  - `q` (`string`, opsional: pencarian nama atau `road_reference_code`).
  - `include_geometry` (`boolean`, default `true`).
- **Respons Sukses `200 OK`:**
  ```json
  {
    "success": true,
    "message": "Road segments retrieved successfully",
    "data": [
      {
        "id": 1,
        "road_reference_code": "SEG-BSD-001",
        "name": "Jl. Pahlawan Seribu Segmen 01",
        "administrative_area_id": 2,
        "centroid_latitude": -6.301,
        "centroid_longitude": 106.653,
        "length_meters": 221.04,
        "road_function": "arteri",
        "road_authority": "kota",
        "nearest_facility_meters": 120.5,
        "geometry_geojson": {
          "type": "LineString",
          "coordinates": [[106.652, -6.301], [106.654, -6.301]]
        },
        "data_origin": "public_dataset",
        "is_demo": false,
        "last_verified_at": "2026-10-29T10:00:00Z"
      }
    ],
    "meta": {
      "current_page": 1,
      "per_page": 20,
      "total": 1,
      "last_page": 1
    }
  }
  ```

---

### 3.3 `GET /api/road-segments/{id}` (`planned`)
- **Tujuan:** Mengambil detail satu ruas jalan beserta ringkasan jumlah laporan terverifikasi aktif dan penilaian kondisi terakhir (`damage_assessments` berstatus `final`).
- **Autentikasi:** Publik.
- **Parameter Path:** `id` (`int` atau `road_reference_code`).
- **Respons Error:** `404 Not Found` bila ruas tidak ditemukan.

---

### 3.4 `GET /api/reports` (`planned`)
- **Tujuan:** Mengambil daftar laporan kerusakan jalan.
- **Autentikasi & Privasi:**
  - Tamu/Publik hanya menerima laporan dengan `status = 'verified'` dan `is_demo = false`, **tanpa** `reporter_id`, nama/email pelapor, maupun foto dengan `is_public = false`.
  - Petugas & Admin dapat memfilter seluruh status.
  - Untuk keperluan marker peta ringan, hanya atribut ringkas (`report_code`, `damage_type`, `status`, `latitude`, `longitude`, `road_segment_id`, `reported_at`, `is_demo`) yang dikembalikan tanpa memuat foto ukuran penuh.
- **Parameter Query:**
  - `page`, `per_page` (maks `100`).
  - `status` (`submitted|under_review|needs_info|verified|rejected|resolved`).
  - `damage_type` (`string`).
  - `road_segment_id` (`int`).
  - `date_from`, `date_to` (`YYYY-MM-DD`).
  - `bbox` (`minLng,minLat,maxLng,maxLat`).

---

### 3.5 `POST /api/reports` (`planned`)
- **Tujuan:** Mengirim laporan kerusakan jalan baru beserta 1–5 foto bukti.
- **Autentikasi:** `auth:sanctum` (peran `reporter`, `officer`, `admin`), dilindungi throttle. Mendukung header opsional `Idempotency-Key`.
- **Request Body (`multipart/form-data`):**
  - `damage_type` (`string`, wajib).
  - `description` (`string`, wajib, 10–1000 karakter).
  - `latitude` (`numeric`, wajib, $-90 \le \text{lat} \le 90$ dan berada di dalam poligon wilayah studi).
  - `longitude` (`numeric`, wajib, $-180 \le \text{lng} \le 180$ dan berada di dalam poligon wilayah studi).
  - `gps_accuracy_m` (`numeric`, opsional, $> 0$).
  - `reported_at` (`ISO 8601 datetime`, opsional, tidak boleh di masa depan).
  - `photos[]` (1–5 berkas gambar `jpg|png|webp`, maks 5 MB per berkas, MIME diverifikasi dari isi berkas).
- **Efek Samping & Transaksi Database:**
  - Berjalan di dalam `DB::transaction()`:
    1. Menyimpan baris `damage_reports` dengan `status = 'submitted'`, `report_code` unik, dan kandidat `road_segment_id` terdekat (radius awal 30 m).
    2. Menyimpan metadata tiap foto ke `report_photos` (`is_public = false`, nama file acak di luar webroot).
    3. Menulis baris awal ke `report_status_histories` (`previous_status = null`, `new_status = 'submitted'`).
    4. Menulis jejak ke `audit_logs`.
- **Respons:** `201 Created` (sukses), `401`, `422` (koordinat di luar wilayah studi / foto tidak valid), `429`.

---

### 3.6 `GET /api/reports/{id}` (`planned`)
- **Tujuan:** Mengambil detail satu laporan berdasarkan `report_code` (atau `id` untuk internal).
- **Autentikasi & Hak Akses:**
  - Tamu hanya dapat melihat laporan `verified` (tanpa identitas pelapor dan hanya foto dengan `is_public = true`).
  - Pelapor hanya dapat melihat laporan miliknya sendiri (pencegahan IDOR; mengembalikan `403 Forbidden` jika mengakses laporan pelapor lain yang belum publik).
  - Petugas & Admin melihat detail lengkap termasuk `status_histories` dan kandidat laporan duplikat dalam radius 20 m & 30 hari.

---

### 3.7 `PATCH /api/reports/{id}/status` (`planned`)
- **Tujuan:** Memperbarui status laporan sesuai state machine Master PRD §8 (`submitted` $\rightarrow$ `under_review` $\rightarrow$ `verified` / `rejected` / `needs_info`, `verified` $\rightarrow$ `resolved`).
- **Autentikasi:** `auth:sanctum` (peran `officer`, `admin`).
- **Request Body (`application/json`):**
  ```json
  {
    "new_status": "verified",
    "road_segment_id": 1,
    "notes": "Kerusakan lubang terkonfirmasi pada Jl. Pahlawan Seribu Segmen 01."
  }
  ```
- **Validasi:**
  - `new_status` wajib mengikuti transisi valid dari `status` saat ini (jika melanggar $\rightarrow$ `409 Conflict`).
  - `notes` wajib diisi terutama pada transisi ke `rejected` atau `needs_info` (jika kosong $\rightarrow$ `422`).
- **Efek Samping & Transaksi Database:**
  - Berjalan dalam `DB::transaction()`: memperbarui `damage_reports`, menambahkan baris append-only ke `report_status_histories`, dan mencatat `audit_logs`.

---

### 3.8 `GET /api/criteria`, `POST /api/criteria`, & `PATCH /api/criteria/{decisionCriterion}` (`implemented`)
- **Tujuan:** Mengambil, membuat, atau memperbarui konfigurasi kriteria keputusan (`decision_criteria`).
- **Autentikasi:** `auth:sanctum` (`GET`: `officer`, `expert`, `admin`; `POST` & `PATCH`: `admin`).
- **Aturan Immutability Historis (`PATCH /api/criteria/{decisionCriterion}`):**
  - Jika kriteria telah direferensikan pada `criterion_weight_items` atau `ahp_pairwise_comparisons`, perubahan pada atribut semantik (`code`, `criterion_type`, `data_type`, `minimum_value`, `maximum_value`) ditolak dengan **`409 Conflict`** (`error_code: "HISTORICAL_CRITERION_IMMUTABLE"`). Perubahan metadata non-semantik (`description`, `is_active`) diizinkan dan dicatat ke `audit_logs`.

---

### 3.9 `GET /api/assessments` & `POST /api/assessments` (`implemented`)
- **Tujuan:** Mencatat dan menampilkan hasil penilaian kondisi fisik ruas jalan di lapangan (`damage_assessments`).
- **Autentikasi:** `auth:sanctum` (`GET`: `officer`, `expert`, `admin`; `POST`: `officer`, `admin`).
- **Request Body (`POST /api/assessments`):**
  ```json
  {
    "road_segment_id": 1,
    "severity_level": 4,
    "safety_risk_score": 3,
    "road_function_score": 3,
    "impact_score": 3,
    "status": "final",
    "assessment_source": "field_inspection"
  }
  ```

---

### 3.10 `GET /api/criterion-weight-versions`, `GET /api/criterion-weight-versions/{id}`, `POST /api/criterion-weight-versions`, `POST /api/criterion-weight-versions/{id}/calculate`, & `POST /api/criterion-weight-versions/{id}/activate` (`implemented`)
- **Tujuan:** Mengelola siklus hidup versi bobot AHP (`draft` $\to$ `computed` $\to$ `approved`).
- **Autentikasi & Otorisasi:**
  - `GET`: `officer`, `expert`, `admin`
  - `POST /api/criterion-weight-versions` (buat draft) & `POST /api/criterion-weight-versions/{id}/calculate` (hitung AHP): `expert`, `admin`
  - `POST /api/criterion-weight-versions/{id}/activate` (setujui & aktifkan): `admin`
- **Aturan Bisnis & Validasi:**
  - `calculate` menerima `comparisons` berisi pasangan `{criterion_a_id, criterion_b_id, comparison_value, expert_id?}` pada skala Saaty $1\text{–}9$ atau resiprokalnya, menghitung eigenvector utama, $\lambda_{\max}$, $CI$, dan $CR$.
  - Versi bobot yang sudah berstatus `approved` atau pernah dipakai pada `decision_runs` bersifat **immutable** (`409 Conflict` jika dihitung ulang).
  - `activate` menolak versi bobot dengan $CR > 0{,}10$ (`422 Unprocessable Entity`, `error_code: "weight_version_inconsistent"`).

---

### 3.11 `GET /api/decision-data-readiness` (`implemented`)
- **Tujuan:** Menghasilkan laporan kesiapan data alternatif ruas jalan sebelum perhitungan TOPSIS dijalankan (PRD 05 §2 & §7).
- **Autentikasi:** `auth:sanctum` (`officer`, `expert`, `admin`).
- **Respons Sukses `200 OK`:** memuat `missing_data_policy`, `criteria`, `summary` (`active_criteria_count`, `total_active_segments`, `eligible_segments_count`, `incomplete_segments_count`, `ready_for_topsis_run`), `eligible_segments`, dan `incomplete_segments` beserta kode alasan (`missing_or_invalid:{code}` / `out_of_range:{code}`).

---

### 3.12 `POST /api/decision-runs` (`implemented`)
- **Tujuan:** Menjalankan satu eksekusi perhitungan prioritas AHP-TOPSIS dan menyimpan snapshot lengkapnya.
- **Autentikasi:** `auth:sanctum` (peran `officer`, `expert`, `admin`).
- **Request Body (`application/json`):**
  ```json
  {
    "name": "Prioritas Penanganan Triwulan IV 2026",
    "criterion_weight_version_id": 1,
    "is_simulation": false,
    "parameters": {
      "report_window_days": 90
    }
  }
  ```
- **Validasi & Aturan Bisnis:**
  - Jika `is_simulation = false`, versi bobot wajib berstatus `approved` dengan `is_consistent = true` ($CR \le 0{,}10$), dan seluruh ruas/data berlabel `is_demo = true` dikeluarkan dari kandidat resmi (`SnapshotService`).
  - Minimal 2 ruas jalan memenuhi syarat kelayakan; jika kurang, kembalikan `422 Unprocessable Entity` (`insufficient_alternatives`).
- **Efek Samping & Transaksi Database:**
  - Berjalan dalam `DB::transaction()`:
    1. Mengeksekusi `SnapshotService::createSnapshot()` dan `SensitivityService::analyze()`.
    2. Menyimpan rekaman induk ke `decision_runs` (termasuk `input_hash` SHA-256 dan `input_snapshot_reference`).
    3. Menyimpan seluruh ruas (baik yang masuk peringkat maupun yang dikeluarkan beserta `exclusion_reason`) ke `decision_run_items`.
    4. Mencatat aksi ke `audit_logs`.

---

### 3.13 `GET /api/decision-runs` & `GET /api/decision-runs/{id}` (`implemented`)
- **Tujuan:**
  - `GET /api/decision-runs`: daftar riwayat eksekusi keputusan.
  - `GET /api/decision-runs/{id}`: detail lengkap satu decision run yang dibaca langsung dari snapshot (`decision_runs` + `decision_run_items`) sekaligus memverifikasi ulang reproduktibilitas melalui `SnapshotService::reproduce()` (`reproducibility_verified = true`), mencakup peringkat, nilai mentah ($X$), nilai ternormalisasi ($R$), nilai terbobot ($V$), $A^+$, $A^-$, $D^+$, $D^-$, $C_i$, `largest_driving_criterion`, `sensitivity_summary`, daftar ruas yang dikecualikan beserta alasan, `input_hash`, dan teks `disclaimer` wajib.
- **Autentikasi:** `auth:sanctum` (peran `officer`, `expert`, `admin`).

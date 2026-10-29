# Kontrak API (`API_CONTRACT.md`)

**Tahap:** PRD 02 — Database Design & API Contract  
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
  "data": {},
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 100,
    "last_page": 5
  }
}
```
*(Objek `meta` disertakan pada endpoint daftar berpaginasi).*

### 1.2 Respons Error Konsisten
```json
{
  "success": false,
  "message": "Data yang dikirim tidak valid.",
  "error": {
    "code": "validation_error",
    "details": {
      "latitude": ["Latitude harus berada di antara -90 dan 90."]
    }
  }
}
```

| Kode HTTP | `error.code` | Makna |
|---|---|---|
| `200 OK` / `201 Created` | - | Permintaan berhasil diproses / resource berhasil dibuat |
| `401 Unauthorized` | `unauthenticated` | Belum login atau token Sanctum tidak valid |
| `403 Forbidden` | `forbidden` | Peran pengguna tidak memiliki izin (Policy menolak akses) |
| `404 Not Found` | `not_found` | Resource tidak ditemukan |
| `409 Conflict` | `invalid_state_transition` | Transisi status laporan melanggar state machine atau versi bobot sudah immutable |
| `422 Unprocessable Entity` | `validation_error` | Validasi input gagal atau aturan kelayakan bisnis tidak terpenuhi |
| `429 Too Many Requests` | `rate_limit_exceeded` | Melebihi batas throttle |
| `500 Internal Server Error` | `server_error` | Kesalahan server (tanpa membocorkan stack trace / `.env`) |

---

## 2. Ringkasan Status Endpoint

> Sesuai PRD 02 §8: hanya `GET /api/health` yang aktif pada tahap ini. Endpoint lainnya berstatus **`planned`** untuk diimplementasikan pada PRD 03–06 dan sengaja tidak dibuat sebagai controller palsu.

| Metode | Path | Akses / Peran | Status | Target Tahap |
|---|---|---|---|---|
| `GET` | `/api/health` | Publik | **`implemented`** | PRD 01 |
| `POST` | `/api/auth/register` | Tamu (throttle) | `planned` | PRD 03 |
| `POST` | `/api/auth/login` | Tamu (throttle) | `planned` | PRD 03 |
| `POST` | `/api/auth/logout` | Login (`auth:sanctum`) | `planned` | PRD 03 |
| `GET` | `/api/me` | Login (`auth:sanctum`) | `planned` | PRD 03 |
| `GET` | `/api/road-segments` | Publik | `planned` | PRD 04 |
| `GET` | `/api/road-segments/{id}` | Publik | `planned` | PRD 04 |
| `GET` | `/api/reports` | Publik (hanya `verified`) / Petugas & Admin (semua) | `planned` | PRD 04 |
| `POST` | `/api/reports` | Pelapor, Petugas, Admin (`auth:sanctum`, throttle) | `planned` | PRD 04 |
| `GET` | `/api/reports/{id}` | Publik (terbatas jika `verified`) / Pemilik / Petugas & Admin | `planned` | PRD 04 |
| `PATCH` | `/api/reports/{id}/status` | Petugas, Admin | `planned` | PRD 04 |
| `GET` | `/api/criteria` | Login (`auth:sanctum`) | `planned` | PRD 05 |
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

### 3.8 `GET /api/criteria` (`planned`)
- **Tujuan:** Mengambil daftar kriteria keputusan (`decision_criteria`).
- **Autentikasi:** `auth:sanctum` (semua peran terautentikasi).
- **Respons Sukses `200 OK`:** daftar kriteria beserta `code`, `name`, `description`, `data_type`, `unit`, `criterion_type` (`benefit`/`cost`), `minimum_value`, `maximum_value`, `aggregation_rule`, `is_required`, `is_active`.

---

### 3.9 `GET /api/criterion-weight-versions` (`planned`)
- **Tujuan:** Mengambil daftar versi bobot AHP (`criterion_weight_versions`) beserta rincian bobot per kriteria (`criterion_weight_items`) dan indikator konsistensi ($\lambda_{\max}$, `consistency_index`, `consistency_ratio`, `is_consistent`).
- **Autentikasi:** `auth:sanctum` (peran `officer`, `expert`, `admin`).

---

### 3.10 `POST /api/decision-runs` (`planned`)
- **Tujuan:** Menjalankan satu eksekusi perhitungan prioritas AHP-TOPSIS dan menyimpan snapshot lengkapnya.
- **Autentikasi:** `auth:sanctum` (peran `admin`, atau `officer` bila diberi izin).
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
    1. Mengeksekusi `SnapshotService::createSnapshot()`.
    2. Menyimpan rekaman induk ke `decision_runs` (termasuk `input_hash` SHA-256 dan `input_snapshot_reference`).
    3. Menyimpan seluruh ruas (baik yang masuk peringkat maupun yang dikeluarkan beserta `exclusion_reason`) ke `decision_run_items`.
    4. Mencatat aksi ke `audit_logs`.

---

### 3.11 `GET /api/decision-runs` & `GET /api/decision-runs/{id}` (`planned`)
- **Tujuan:**
  - `GET /api/decision-runs`: daftar riwayat eksekusi keputusan berpaginasi.
  - `GET /api/decision-runs/{id}`: detail lengkap satu decision run yang dibaca langsung dari snapshot (`decision_runs` + `decision_run_items`), mencakup peringkat, nilai mentah, nilai ternormalisasi, nilai terbobot, $A^+$, $A^-$, $D^+$, $D^-$, $C_i$, `largest_driving_criterion`, daftar ruas yang dikecualikan beserta alasan, `input_hash`, dan teks `disclaimer` wajib.
- **Autentikasi:** `auth:sanctum` (peran `officer`, `expert`, `admin`).

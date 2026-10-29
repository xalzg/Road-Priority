# Kebijakan Visibilitas Data & Privasi (`DATA_VISIBILITY.md`)

**Tahap:** PRD 04 — Road Damage Reporting & WebGIS  
**Penegakan:** [`DamageReportPolicy.php`](../backend/app/Policies/DamageReportPolicy.php), [`ReportAccessController.php`](../backend/app/Http/Controllers/Api/ReportAccessController.php), [`RoadSegmentController.php`](../backend/app/Http/Controllers/Api/RoadSegmentController.php)

---

## 1. Prinsip Dasar Perlindungan Data (`PRD 04 §3 & §11`)

1. **Kontrol Akses di Server:** Seluruh penyaringan status, penyensoran field sensitif, dan pemeriksaan kepemilikan resource (anti-IDOR) dilakukan di backend Laravel. Menyembunyikan tombol di React bukan kontrol keamanan.
2. **Pemisahan Data Demonstrasi (`is_demo`):** Baris `damage_reports` dan `road_segments` dengan `is_demo = true` (hasil `DemoSimulationSeeder`) **tidak pernah** dikembalikan pada endpoint publik. Data demo hanya dapat diminta secara eksplisit (`include_demo=1`) oleh pengguna terautentikasi ber-role `officer`, `expert`, atau `admin` untuk keperluan pengujian/simulasi.

---

## 2. Matriks Visibilitas Laporan (`damage_reports`)

| Aktor Pengakses | Laporan `verified` / `resolved` (`is_demo = false`) | Laporan `submitted` / `under_review` / `needs_info` / `rejected` / `duplicate` |
|---|---|---|
| **Pengunjung Publik (Tamu)** | **Diizinkan (`200 OK`)** — hanya field publik | **Ditolak** (`401 Unauthorized` pada detail, `403 Forbidden` jika memfilter status privat di daftar) |
| **Pelapor (`reporter`) — Pemilik Laporan** | **Diizinkan (`200 OK`)** — melihat `reporter_id` miliknya sendiri & catatan status | **Diizinkan (`200 OK`)** — dapat memantau laporan miliknya sendiri (`mine=1` atau detail) |
| **Pelapor (`reporter`) — Bukan Pemilik** | **Diizinkan (`200 OK`)** — hanya field publik (`reporter_id = null`) | **Ditolak (`403 Forbidden`)** — proteksi anti-IDOR |
| **Pakar (`expert`)** | **Diizinkan (`200 OK`)** — hanya field publik (`reporter_id = null`) | **Ditolak (`403 Forbidden`)** |
| **Petugas (`officer`) & Admin (`admin`)** | **Diizinkan (`200 OK`)** — akses penuh + bantuan verifikasi | **Diizinkan (`200 OK`)** — akses penuh + bantuan verifikasi |

---

## 3. Daftar Field Publik vs Field Terbatas pada `GET /api/reports/{id}`

| Nama Field | Publik / Pelapor Lain | Pelapor Pemilik (`reporter_id == user.id`) | Petugas (`officer`) & `admin` |
|---|---|---|---|
| `id`, `report_code`, `damage_type`, `description` | Ditampilkan | Ditampilkan | Ditampilkan |
| `latitude`, `longitude`, `gps_accuracy_m` | Ditampilkan | Ditampilkan | Ditampilkan |
| `road_segment_id`, `road_segment`, `segment_match_distance_m` | Ditampilkan | Ditampilkan | Ditampilkan |
| `status`, `reported_at`, `verified_at` | Ditampilkan | Ditampilkan | Ditampilkan |
| `reporter_id` | **`null` (Disensor)** | Ditampilkan (ID miliknya) | Ditampilkan |
| `verification_notes` | **`null` (Disensor)** | Ditampilkan (agar tahu alasan tolak/butuh info) | Ditampilkan |
| `verified_by`, `status_histories[].changed_by` | **`null` (Disensor)** | **`null` (Disensor)** | Ditampilkan |
| `status_histories[].notes` | **`null` (Disensor)** | Ditampilkan | Ditampilkan |
| `photos[]` | Hanya foto dengan `is_public = true` | Seluruh foto laporan miliknya | Seluruh foto laporan |
| `suggested_segments`, `nearby_duplicate_candidates`, `study_area_check` | Tidak disertakan | Tidak disertakan | **Disertakan** |

---

## 4. Aturan Visibilitas File Foto (`GET /api/reports/{damageReport}/photos/{reportPhoto}`)

1. File fisik disimpan di luar `public/` (pada `storage/app/report-photos/...`).
2. Setiap permintaan unduh/tampil foto melewati `ReportAccessController::showPhoto` dan `DamageReportPolicy::viewPhoto`:
   - Jika laporan berstatus `verified` atau `resolved`, `is_demo = false`, **dan** `report_photos.is_public = true`, foto dapat diakses publik (`200 OK`).
   - Jika salah satu syarat di atas tidak terpenuhi, foto bersifat **privat**:
     - Tamu tanpa token menerima `401 Unauthorized`.
     - Pengguna lain yang bukan pemilik laporan dan bukan `officer`/`admin` menerima `403 Forbidden`.
     - Pemilik laporan, `officer`, dan `admin` aktif menerima stream gambar (`200 OK`) dengan header `X-Content-Type-Options: nosniff`.


# PROJECT_STATUS — RoadPriority

Diperbarui: 2026-10-30 00:21 WIB oleh sesi agen (Antigravity)

## Tahap aktif

- **PRD 01 (Foundation & Project Bootstrap + Audit Sumber Data):** **selesai dan disetujui**.
- **PRD 02 (Database Design & API Contract):** **selesai dan terverifikasi** (`prd-02-database-and-api`).
- **PRD 03 (Backend API, Authentication & Access Control):** **selesai dan terverifikasi** (`prd-03-backend-auth-rbac`).
- **PRD 04 (Road Damage Reporting & WebGIS):** **selesai dan terverifikasi** (`prd-04-reporting-and-webgis`).
- **PRD 05 (Decision Engine AHP-TOPSIS):** **selesai diimplementasikan dan diverifikasi penuh** pada cabang `prd-05-decision-engine-integration` (menunggu persetujuan penutupan tahap dari pengguna).

## Ringkasan kondisi

1. **Mesin Pendukung Keputusan Hibrida AHP-TOPSIS (PRD 05):**
   - **Kriteria & Immutability Historis (`DecisionCriterionController`):** Mendukung pembacaan, penambahan, dan pembaruan kriteria (`GET/POST/PATCH /api/criteria`) dengan proteksi `409 Conflict` (`HISTORICAL_CRITERION_IMMUTABLE`) jika kriteria yang sudah dipakai pada versi bobot atau perhitungan historis hendak diubah atribut semantiknya (`code`, `criterion_type`, `data_type`, `minimum_value`, `maximum_value`).
   - **Perbandingan Berpasangan AHP & Siklus Hidup Versi Bobot (`CriterionWeightVersionService`):** Mendukung pembuatan draft (`POST /api/criterion-weight-versions`), perhitungan bobot eigenvector utama, $\lambda_{\max}$, $CI$, dan $CR$ pada skala Saaty $1\text{–}9$ beserta agregasi rata-rata geometrik multi-pakar (`POST /api/criterion-weight-versions/{id}/calculate`), serta persetujuan/aktivasi versi bobot oleh Admin (`POST /api/criterion-weight-versions/{id}/activate`) yang menolak aktivasi bila $CR > 0{,}10$ (`422 weight_version_inconsistent`) dan mengunci versi yang telah disetujui (`409 Conflict`).
   - **Laporan Kesiapan Data & Penanganan Data Hilang (`DecisionDataProvider`):** Endpoint `GET /api/decision-data-readiness` menyajikan matriks kesiapan nilai kriteria per ruas jalan (`K1`–`K7`), memisahkan segmen layak (`eligible_segments`) dari segmen tidak lengkap (`incomplete_segments`) beserta kode alasan (`missing_or_invalid:{code}` / `out_of_range:{code}`) tanpa melakukan imputasi nol/rata-rata diam-diam (`D-09`).
   - **Eksekusi TOPSIS, Penjelasan Peringkat, Sensitivitas & Reproduktibilitas Snapshot (`DecisionRunService`, `SnapshotService`, `SensitivityService`):** Endpoint `POST /api/decision-runs`, `GET /api/decision-runs`, dan `GET /api/decision-runs/{id}` menyimpan snapshot beku ($X, R, V, A^+, A^-, D_i^+, D_i^-, C_i$, `largest_driving_criterion`, ruas yang dikeluarkan beserta alasan, ringkasan sensitivitas OAT $\pm 10\%, \pm 20\%$ & bobot sama rata Spearman $\rho$, hash input SHA-256, dan teks *disclaimer* wajib) serta memverifikasi ulang reproduktibilitas (`reproducibility_verified = true`) saat dibaca.
   - **Antarmuka React AHP-TOPSIS (`DecisionEnginePanel.jsx`):** Menyediakan 4 sub-tampilan terintegrasi di `HomePage.jsx` untuk manajemen kriteria & kesiapan data (+ pencatatan `DamageAssessment` lapangan), form perbandingan berpasangan AHP & aktivasi versi bobot, eksekusi & riwayat TOPSIS, serta tabel rincian matriks $X \to R \to V$, solusi ideal $A^+/A^-$, jarak $D_i^+/D_i^-$, dan skor $C_i$.

## Hasil build dan tes terakhir

| Perintah | Hasil | Tanggal |
|---|---|---|
| `php artisan test` (pada `roadpriority_test`) | **`113 passed` (0 failed)** dalam `5.69s` (`AhpServiceTest` 32 tes, `TopsisServiceTest` 32 tes, `SensitivityServiceTest` 7 tes, `SnapshotServiceTest` 5 tes, `DecisionEngineIntegrationTest` 3 tes end-to-end, `DamageReportingAndWebGisTest` 6 tes, `AuthAndAccessControlTest` 16 tes, `DatabaseSchemaIntegrityTest` 8 tes, `HealthEndpointTest` 2 tes, `ExampleTest` 2 tes) | 2026-10-30 00:15 WIB |
| `npm run lint` (`frontend/`) | `Found 0 warnings and 0 errors` (14 berkas) | 2026-10-30 00:18 WIB |
| `npm run build` (`frontend/`) | `✓ built in 358ms` (`dist/assets/index-6THUM4F_.js` 439.10 kB, `index-BK8-Xho-.css` 24.27 kB) | 2026-10-30 00:18 WIB |

## Requirement dan acceptance criteria PRD 05

| Kriteria (PRD 05 §16) | Status | Bukti |
|---|---|---|
| Kriteria dan tipe benefit/cost terdokumentasi dan tervalidasi | **Terpenuhi** | `DecisionCriterionController`, `UpdateDecisionCriterionRequest`, [`docs/DECISION_DATA_READINESS.md`](DECISION_DATA_READINESS.md) |
| Matriks AHP tervalidasi dan bobot berjumlah `1,0` | **Terpenuhi** | `AhpService`, `CriterionWeightVersionService`, `AhpServiceTest` |
| CI dan CR dihitung dan diuji terhadap ambang konsistensi ($\le 0{,}10$) | **Terpenuhi** | `AhpService::computeWeights`, `CriterionWeightVersionService::activateVersion`, `test_inconsistent_ahp_matrix_cannot_be_activated_or_used_in_official_run` |
| Penilaian tidak konsisten tidak dapat diaktifkan sebagai bobot resmi | **Terpenuhi** | `422 weight_version_inconsistent` pada `activateVersion` & `SnapshotService::validateWeightVersionForRun` |
| TOPSIS menghitung normalisasi, pembobotan, solusi ideal, jarak, skor preferensi, dan peringkat dengan benar | **Terpenuhi** | `TopsisService`, `TopsisServiceTest`, `test_full_ahp_topsis_workflow_and_lampiran_c_verification` |
| Kasus batas dan data tidak lengkap ditangani secara eksplisit | **Terpenuhi** | `TopsisService` (`excluded` dengan `missing_or_invalid:{code}` / `out_of_range:{code}`), `DecisionRunItem` (`is_included = false`) |
| Setiap hasil dapat direproduksi dari snapshot historis | **Terpenuhi** | `SnapshotService::reproduce`, `test_historical_snapshot_reproducibility_and_criterion_immutability` |
| Setiap peringkat memiliki penjelasan yang dapat dibaca | **Terpenuhi** | `DecisionRunService` (`explanation`, `largest_driving_criterion`, `source_report_ids`), `DecisionEnginePanel.jsx` |
| Perhitungan diverifikasi terhadap contoh perhitungan independen | **Terpenuhi** | `backend/tests/Reference/ahp_topsis_reference.py`, `backend/tests/Fixtures/reference.json`, Lampiran C (`A2 > A1 > A3 > A4`) |
| Test unit dan feature lulus | **Terpenuhi** | `113 passed` pada `roadpriority_test`, `0 warnings / 0 errors` pada `npm run lint` |
| Dokumentasi dan `PROJECT_STATUS.md` diperbarui | **Terpenuhi** | [`docs/DECISION_METHOD.md`](DECISION_METHOD.md), [`docs/AHP_METHODOLOGY.md`](AHP_METHODOLOGY.md), [`docs/TOPSIS_METHODOLOGY.md`](TOPSIS_METHODOLOGY.md), [`docs/DECISION_DATA_READINESS.md`](DECISION_DATA_READINESS.md), [`docs/API_CONTRACT.md`](API_CONTRACT.md), [`docs/DECISIONS.md`](DECISIONS.md), dan dokumen ini |

## Issue terbuka & Batasan Data

| ID | Keparahan | Deskripsi | Langkah reproduksi / Mitigasi |
|---|---|---|---|
| ISS-01 | Sedang | PHP 8.0.30 & Laravel 9.52.22 memiliki 19 advisory keamanan pada `composer audit` (`DEC-002`) | Aman untuk pengembangan lokal; wajib upgrade PHP $\ge 8.2$ sebelum deployment publik |
| ISS-02 | Rendah | Poligon batas administratif resmi BIG (`boundary_geojson`), ekstrak jalan OSM BSD penuh, serta penilaian pakar AHP lapangan sesungguhnya belum dimuat ke database produksi (`D-02`, `D-03`, `D-06`, `D-07`) | Mesin keputusan telah terverifikasi penuh secara matematis dan struktural menggunakan fixture referensi Lampiran C; siap menerima input kuesioner pakar dan asesmen lapangan sesungguhnya |

## Langkah berikutnya yang disarankan

1. Setujui penutupan **PRD 05**.
2. Lanjutkan ke tahap berikutnya (**PRD 06 — Dashboard, Visualisasi Prioritas WebGIS & Pelaporan Eksekutif**).
